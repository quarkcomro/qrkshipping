<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

/** @return list<string> */
function phpFiles(string $directory): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);

    return $files;
}

$layerRules = [
    'Domain' => [
        '/PrestaShop\\\\/i',
        '/Symfony\\\\/i',
        '/\\b(?:Db|Configuration|Context|Tools)::/',
        '/\\b(?:curl_[a-z_]+|file_get_contents|file_put_contents|fopen|fsockopen)\\s*\\(/i',
        '/\\$_(?:GET|POST|REQUEST|SERVER|COOKIE|FILES|ENV)\\b/',
    ],
    'Application' => [
        '/PrestaShop\\\\/i',
        '/Symfony\\\\/i',
        '/\\b(?:Db|Configuration|Context|Tools)::/',
        '/\\b(?:curl_[a-z_]+|file_get_contents|file_put_contents|fopen|fsockopen)\\s*\\(/i',
        '/\\$_(?:GET|POST|REQUEST|SERVER|COOKIE|FILES|ENV)\\b/',
    ],
];

foreach ($layerRules as $layer => $patterns) {
    foreach (phpFiles($root . '/src/' . $layer) as $file) {
        $content = (string) file_get_contents($file);
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                $errors[] = sprintf('%s violates %s layer rule %s', $file, $layer, $pattern);
            }
        }
    }
}

foreach (phpFiles($root . '/src/Controller') as $file) {
    $content = (string) file_get_contents($file);
    if (preg_match('/\\b(?:Db|Configuration)::|SELECT\\s|INSERT\\s|UPDATE\\s|DELETE\\s|curl_/i', $content) === 1) {
        $errors[] = $file . ': controller contains direct persistence or HTTP code';
    }
}

$entrypoint = (string) file_get_contents($root . '/qrkshipping.php');
foreach (['getOrderShippingCost', 'getOrderShippingCostExternal'] as $method) {
    $pattern = '/public function ' . preg_quote($method, '/') . '\\([^)]*\\)\\s*\\{\\s*return false;\\s*\\}/s';
    if (preg_match($pattern, $entrypoint) !== 1) {
        $errors[] = $method . ': cost method is not the approved inert implementation';
    }
}

$schema = (string) file_get_contents($root . '/src/Infrastructure/Persistence/Schema/SchemaCatalog.php');
preg_match_all("/new TableDefinition\\(\\s*'([^']+)'/", $schema, $matches);
$expectedTables = [
    'qrkship_schema_migration',
    'qrkship_setting',
    'qrkship_secret',
    'qrkship_provider_account',
    'qrkship_audit_event',
];
if (($matches[1] ?? []) !== $expectedTables) {
    $errors[] = 'SchemaCatalog does not contain exactly the five approved tables in authority order.';
}

$allSource = '';
foreach (phpFiles($root . '/src') as $file) {
    $allSource .= (string) file_get_contents($file) . "\n";
}
foreach (['cargus_ps91', 'qrkshipping_v1', 'PrestaShop\\Module\\Cargus', 'FanCourier'] as $donorMarker) {
    if (stripos($allSource, $donorMarker) !== false) {
        $errors[] = 'Controlled-donor marker leaked into production source: ' . $donorMarker;
    }
}

foreach (glob($root . '/views/templates/admin/*.twig') ?: [] as $template) {
    $content = (string) file_get_contents($template);
    if (preg_match('/<(?:script|link|img)\\b[^>]*(?:src|href)=["\']https?:\\/\\//i', $content) === 1) {
        $errors[] = basename($template) . ': remote asset detected';
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "Architecture: layer boundaries, five-table schema, inert cost methods and local assets passed.\n");
