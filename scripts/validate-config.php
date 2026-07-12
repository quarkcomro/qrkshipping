<?php

declare(strict_types=1);

use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaCatalog;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\TableDefinition;
use Qrk\Commerce\Shipping\ModuleMetadata;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Source;

$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Qrk\\Commerce\\Shipping\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    });
}

$root = dirname(__DIR__);
$errors = [];

/**
 * @return array<string, mixed>
 */
function parseTopLevelYamlMapping(string $path): array
{
    $content = (string) file_get_contents($path);
    if (str_contains($content, "\t")) {
        throw new RuntimeException('YAML files must not contain tab indentation.');
    }

    $mapping = [];
    $currentKey = null;
    foreach (preg_split('/\R/', $content) ?: [] as $line) {
        if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
            continue;
        }

        if (preg_match('/^([A-Za-z0-9_.\\-]+):\s*(.*)$/', $line, $matches) === 1) {
            $currentKey = $matches[1];
            $mapping[$currentKey] = [];
            continue;
        }

        if ($currentKey !== null && preg_match('/^  ([A-Za-z0-9_.\\$-]+):\s*(.*)$/', $line, $matches) === 1) {
            $mapping[$currentKey][$matches[1]] = $matches[2];
        }
    }

    return $mapping;
}

/** @var array<string, mixed> $decodedJson */
$decodedJson = [];
foreach (['composer.json', 'schema-manifest.json'] as $jsonFile) {
    $path = $root . '/' . $jsonFile;

    try {
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            $errors[] = $jsonFile . ': expected a JSON object';
            continue;
        }

        $decodedJson[$jsonFile] = $decoded;
    } catch (Throwable $exception) {
        $errors[] = $jsonFile . ': ' . $exception->getMessage();
    }
}

/** @var array<string, mixed> $decodedYaml */
$decodedYaml = [];
foreach (['config/routes.yml', 'config/services.yml'] as $yamlFile) {
    try {
        if (class_exists(Yaml::class)) {
            $parsed = Yaml::parseFile($root . '/' . $yamlFile);
        } else {
            $parsed = parseTopLevelYamlMapping($root . '/' . $yamlFile);
        }

        if (!is_array($parsed) || $parsed === []) {
            $errors[] = $yamlFile . ': expected a non-empty mapping';
            continue;
        }

        $decodedYaml[$yamlFile] = $parsed;
    } catch (Throwable $exception) {
        $errors[] = $yamlFile . ': ' . $exception->getMessage();
    }
}

$routes = $decodedYaml['config/routes.yml'] ?? null;
if (is_array($routes)) {
    $expectedRoutes = [
        'admin_qrkshipping_dashboard',
        'admin_qrkshipping_preferences',
        'admin_qrkshipping_preferences_update',
        'admin_qrkshipping_help',
        'admin_qrkshipping_diagnostics',
    ];

    if (array_keys($routes) !== $expectedRoutes) {
        $errors[] = 'config/routes.yml: route set or authority order differs from the approved contract';
    }

    foreach ($routes as $routeName => $route) {
        if (!is_array($route) || !isset($route['path'], $route['methods'], $route['defaults'])) {
            $errors[] = sprintf('config/routes.yml: route "%s" is incomplete', (string) $routeName);
        }
    }
}

$upgradeFunction = 'upgrade_module_' . str_replace('.', '_', ModuleMetadata::VERSION);
$upgradeFile = $root . '/upgrade/upgrade-' . ModuleMetadata::VERSION . '.php';
if (!is_file($upgradeFile)) {
    $errors[] = 'upgrade: current module version has no upgrade entrypoint';
} else {
    $upgradeContent = (string) file_get_contents($upgradeFile);
    if (!str_contains($upgradeContent, 'function ' . $upgradeFunction . '(')) {
        $errors[] = 'upgrade: current module version function name is invalid';
    }
}

$xlfFiles = glob($root . '/translations/*.xlf') ?: [];
$xmlFiles = [$root . '/config.xml', ...$xlfFiles];

/** @var array<string, array<string, array<string, true>>> $translationSources */
$translationSources = [];
foreach ($xmlFiles as $xmlFile) {
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->load($xmlFile, LIBXML_NONET | LIBXML_NOBLANKS);
    $xmlErrors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded || $xmlErrors !== []) {
        $errors[] = basename($xmlFile) . ': invalid XML';
        continue;
    }

    if (!str_ends_with($xmlFile, '.xlf')) {
        continue;
    }

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');
    $fileNodes = $xpath->query('/x:xliff/x:file');

    if ($fileNodes === false || $fileNodes->length !== 1) {
        $errors[] = basename($xmlFile) . ': expected exactly one XLIFF file element';
        continue;
    }

    $fileNode = $fileNodes->item(0);
    if (!$fileNode instanceof DOMElement) {
        $errors[] = basename($xmlFile) . ': XLIFF file element is invalid';
        continue;
    }

    $domain = $fileNode->getAttribute('original');
    $language = $fileNode->getAttribute('target-language');
    $expectedPattern = '/^ModulesQrkshipping([A-Za-z]+)\.(en-US|ro-RO)\.xlf$/D';

    if (preg_match($expectedPattern, basename($xmlFile), $matches) !== 1) {
        $errors[] = basename($xmlFile) . ': filename does not follow the module XLF convention';
        continue;
    }

    $expectedDomain = 'Modules.Qrkshipping.' . $matches[1];
    if ($domain !== $expectedDomain || $language !== $matches[2]) {
        $errors[] = basename($xmlFile) . ': declared domain or language does not match its filename';
    }

    $units = $xpath->query('/x:xliff/x:file/x:body/x:trans-unit');
    if ($units === false || $units->length === 0) {
        $errors[] = basename($xmlFile) . ': translation catalog is empty';
        continue;
    }

    foreach ($units as $unit) {
        if (!$unit instanceof DOMElement) {
            continue;
        }

        $sourceNodes = $xpath->query('x:source', $unit);
        $targetNodes = $xpath->query('x:target', $unit);
        $sourceNode = $sourceNodes === false ? null : $sourceNodes->item(0);
        $targetNode = $targetNodes === false ? null : $targetNodes->item(0);
        $source = $sourceNode instanceof DOMNode ? trim($sourceNode->textContent) : '';
        $target = $targetNode instanceof DOMNode ? trim($targetNode->textContent) : '';

        if ($source === '' || $target === '') {
            $errors[] = basename($xmlFile) . ': empty source or target translation';
            continue;
        }

        if (isset($translationSources[$domain][$language][$source])) {
            $errors[] = basename($xmlFile) . ': duplicate source message "' . $source . '"';
        }

        $translationSources[$domain][$language][$source] = true;
    }
}

foreach ($translationSources as $domain => $languages) {
    foreach (['en-US', 'ro-RO'] as $requiredLanguage) {
        if (!isset($languages[$requiredLanguage])) {
            $errors[] = sprintf('%s: missing %s catalog', $domain, $requiredLanguage);
        }
    }

    if (!isset($languages['en-US'], $languages['ro-RO'])) {
        continue;
    }

    $english = array_keys($languages['en-US']);
    $romanian = array_keys($languages['ro-RO']);
    sort($english);
    sort($romanian);

    if ($english !== $romanian) {
        $errors[] = $domain . ': EN and RO catalogs do not contain the same source messages';
    }
}

$manifest = $decodedJson['schema-manifest.json'] ?? null;
if (is_array($manifest)) {
    $catalog = new SchemaCatalog();
    $expectedTables = array_map(
        static fn (TableDefinition $table): array => $table->canonical(),
        $catalog->tables(),
    );

    if (($manifest['schema_version'] ?? null) !== ModuleMetadata::SCHEMA_VERSION) {
        $errors[] = 'schema-manifest.json: schema version differs from ModuleMetadata';
    }
    if (($manifest['fingerprint'] ?? null) !== $catalog->fingerprint()) {
        $errors[] = 'schema-manifest.json: fingerprint differs from SchemaCatalog';
    }
    if (($manifest['migration_checksum'] ?? null) !== $catalog->migrationChecksum()) {
        $errors[] = 'schema-manifest.json: migration checksum differs from SchemaCatalog';
    }
    if (($manifest['tables'] ?? null) !== $expectedTables) {
        $errors[] = 'schema-manifest.json: table definitions differ from SchemaCatalog';
    }
}

$configDocument = new DOMDocument();
if ($configDocument->load($root . '/config.xml', LIBXML_NONET | LIBXML_NOBLANKS)) {
    $configXpath = new DOMXPath($configDocument);
    $moduleName = trim((string) $configXpath->evaluate('string(/module/name)'));
    $moduleVersion = trim((string) $configXpath->evaluate('string(/module/version)'));
    $minimumVersion = trim((string) $configXpath->evaluate('string(/module/ps_versions_compliancy/min)'));

    if ($moduleName !== ModuleMetadata::NAME) {
        $errors[] = 'config.xml: module name differs from ModuleMetadata';
    }
    if ($moduleVersion !== ModuleMetadata::VERSION) {
        $errors[] = 'config.xml: module version differs from ModuleMetadata';
    }
    if ($minimumVersion !== ModuleMetadata::MIN_PRESTASHOP_VERSION) {
        $errors[] = 'config.xml: minimum PrestaShop version differs from ModuleMetadata';
    }
}

$twigTemplates = glob($root . '/views/templates/admin/*.twig') ?: [];
$twigSyntaxMode = 'fallback structural scan';
if (class_exists(Environment::class) && class_exists(ArrayLoader::class) && class_exists(Source::class)) {
    $twigSyntaxMode = 'Twig parser';
    $twig = new Environment(new ArrayLoader(), ['strict_variables' => true]);
    foreach ($twigTemplates as $template) {
        try {
            $source = new Source((string) file_get_contents($template), basename($template));
            $twig->parse($twig->tokenize($source));
        } catch (Throwable $exception) {
            $errors[] = basename($template) . ': ' . $exception->getMessage();
        }
    }
} else {
    foreach ($twigTemplates as $template) {
        $content = (string) file_get_contents($template);
        if (substr_count($content, '{%') !== substr_count($content, '%}')) {
            $errors[] = basename($template) . ': unmatched Twig block delimiters';
        }
        if (substr_count($content, '{{') !== substr_count($content, '}}')) {
            $errors[] = basename($template) . ': unmatched Twig print delimiters';
        }
    }
}

if ($errors !== []) {
    $errors = array_values(array_unique($errors));
    sort($errors);
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Configuration integrity: JSON, YAML, XML/XLF, schema manifest and %d Twig templates passed.\n",
    count($twigTemplates),
));
