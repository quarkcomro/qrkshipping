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

/** @return list<string> */
function xmlTagValues(string $xml, string $tag): array
{
    preg_match_all('/<' . preg_quote($tag, '/') . '(?:\s[^>]*)?>(.*?)<\/' . preg_quote($tag, '/') . '>/s', $xml, $matches);

    return array_map(
        static function (string $value): string {
            $value = preg_replace('/^<!\[CDATA\[(.*)\]\]>$/s', '$1', trim($value)) ?? $value;

            return trim(html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8'));
        },
        $matches[1] ?? [],
    );
}

function xmlAttribute(string $tag, string $attribute): string
{
    if (preg_match('/\b' . preg_quote($attribute, '/') . '=("([^"]*)"|\'([^\']*)\')/', $tag, $matches) !== 1) {
        return '';
    }

    return html_entity_decode($matches[2] !== '' ? $matches[2] : $matches[3], ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function configValue(string $xml, string $tag): string
{
    $values = xmlTagValues($xml, $tag);

    return $values[0] ?? '';
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
}

$configXmlPath = $root . '/config.xml';
$configXml = (string) file_get_contents($configXmlPath);
if (!str_contains($configXml, '<module>') || !str_contains($configXml, '</module>')) {
    $errors[] = 'config.xml: invalid module XML structure';
}
if (preg_match('/<!DOCTYPE|<!ENTITY/i', $configXml) === 1) {
    $errors[] = 'config.xml: external XML entity declarations are forbidden';
}

/** @var array<string, array<string, array<string, true>>> $translationSources */
$translationSources = [];
$xlfFiles = glob($root . '/translations/*.xlf') ?: [];
foreach ($xlfFiles as $xmlFile) {
    $content = (string) file_get_contents($xmlFile);
    $baseName = basename($xmlFile);

    if (preg_match('/<!DOCTYPE|<!ENTITY/i', $content) === 1) {
        $errors[] = $baseName . ': external XML entity declarations are forbidden';
        continue;
    }
    if (!str_contains($content, '<xliff') || !str_contains($content, '</xliff>')) {
        $errors[] = $baseName . ': invalid XLIFF structure';
        continue;
    }

    $expectedPattern = '/^ModulesQrkshipping([A-Za-z]+)\.(en-US|ro-RO)\.xlf$/D';
    if (preg_match($expectedPattern, $baseName, $matches) !== 1) {
        $errors[] = $baseName . ': filename does not follow the module XLF convention';
        continue;
    }

    if (preg_match('/<file\b([^>]*)>/s', $content, $fileMatches) !== 1) {
        $errors[] = $baseName . ': expected exactly one XLIFF file element';
        continue;
    }

    $domain = xmlAttribute($fileMatches[1], 'original');
    $language = xmlAttribute($fileMatches[1], 'target-language');
    $expectedDomain = 'Modules.Qrkshipping.' . $matches[1];
    if ($domain !== $expectedDomain || $language !== $matches[2]) {
        $errors[] = $baseName . ': declared domain or language does not match its filename';
    }

    preg_match_all('/<trans-unit\b[^>]*>(.*?)<\/trans-unit>/s', $content, $unitMatches);
    if (($unitMatches[1] ?? []) === []) {
        $errors[] = $baseName . ': translation catalog is empty';
        continue;
    }

    foreach ($unitMatches[1] as $unit) {
        $source = xmlTagValues($unit, 'source')[0] ?? '';
        $target = xmlTagValues($unit, 'target')[0] ?? '';
        if ($source === '' || $target === '') {
            $errors[] = $baseName . ': empty source or target translation';
            continue;
        }

        if (isset($translationSources[$domain][$language][$source])) {
            $errors[] = $baseName . ': duplicate source message "' . $source . '"';
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

if (configValue($configXml, 'name') !== ModuleMetadata::NAME) {
    $errors[] = 'config.xml: module name differs from ModuleMetadata';
}
if (configValue($configXml, 'version') !== ModuleMetadata::VERSION) {
    $errors[] = 'config.xml: module version differs from ModuleMetadata';
}
if (configValue($configXml, 'min') !== ModuleMetadata::MIN_PRESTASHOP_VERSION) {
    $errors[] = 'config.xml: minimum PrestaShop version differs from ModuleMetadata';
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
    "Configuration integrity: JSON, YAML, XML/XLF, schema manifest and %d Twig templates passed (%s).\n",
    count($twigTemplates),
    $twigSyntaxMode,
));
