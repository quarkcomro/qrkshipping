<?php

declare(strict_types=1);

use Qrk\Commerce\Shipping\ModuleMetadata;

$root = dirname(__DIR__);
$rootAutoload = $root . '/vendor/autoload.php';
if (is_file($rootAutoload)) {
    require_once $rootAutoload;
} else {
    require_once $root . '/src/ModuleMetadata.php';
}

$buildRoot = $root . '/build';
$moduleRoot = $buildRoot . '/qrkshipping';
$distRoot = $root . '/dist';
$epoch = (int) (getenv('SOURCE_DATE_EPOCH') ?: 1783728000);

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
};

$removeTree($buildRoot);
if (!is_dir($moduleRoot) && !mkdir($moduleRoot, 0775, true) && !is_dir($moduleRoot)) {
    throw new RuntimeException('Cannot create build directory.');
}
if (!is_dir($distRoot) && !mkdir($distRoot, 0775, true) && !is_dir($distRoot)) {
    throw new RuntimeException('Cannot create dist directory.');
}

$copyFile = static function (string $source, string $destination): void {
    $parent = dirname($destination);
    if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
        throw new RuntimeException('Cannot create release directory.');
    }
    if (!copy($source, $destination)) {
        throw new RuntimeException('Cannot copy release file: ' . $source);
    }
};

$copyTree = static function (string $source, string $destination) use (&$copyTree, $copyFile): void {
    $entries = scandir($source);
    if ($entries === false) {
        throw new RuntimeException('Cannot read release source directory.');
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $from = $source . '/' . $entry;
        $to = $destination . '/' . $entry;
        if (is_dir($from)) {
            $copyTree($from, $to);
        } else {
            $copyFile($from, $to);
        }
    }
};

foreach ([
    'qrkshipping.php',
    'config.xml',
    'composer.json',
    'schema-manifest.json',
    'README.md',
    'CHANGELOG.md',
    'SECURITY.md',
    'LICENSE.md',
    'SOURCE_LEDGER.md',
    'TO_DO.md',
] as $file) {
    $copyFile($root . '/' . $file, $moduleRoot . '/' . $file);
}
foreach (['config', 'src', 'upgrade', 'views', 'translations'] as $directory) {
    $copyTree($root . '/' . $directory, $moduleRoot . '/' . $directory);
}

$vendorDirectory = $moduleRoot . '/vendor';
if (!is_dir($vendorDirectory) && !mkdir($vendorDirectory, 0775, true) && !is_dir($vendorDirectory)) {
    throw new RuntimeException('Cannot create release vendor directory.');
}

file_put_contents($vendorDirectory . '/autoload.php', <<<'PHP'
<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Qrk\\Commerce\\Shipping\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
PHP);

$scan = proc_open(
    [PHP_BINARY, $root . '/scripts/artifact-scan.php', $moduleRoot, '--release'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $scanPipes,
);
if (!is_resource($scan)) {
    throw new RuntimeException('Cannot start release artifact scan.');
}
$scanOutput = stream_get_contents($scanPipes[1]);
$scanError = stream_get_contents($scanPipes[2]);
fclose($scanPipes[1]);
fclose($scanPipes[2]);
if (proc_close($scan) !== 0) {
    throw new RuntimeException('Release artifact scan failed: ' . trim($scanOutput . "\n" . $scanError));
}

$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if ($file instanceof SplFileInfo && $file->isFile()) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($moduleRoot) + 1));
        if ($relative !== 'MANIFEST.sha256') {
            $files[$relative] = hash_file('sha256', $file->getPathname());
        }
    }
}
ksort($files);
$manifestLines = [];
foreach ($files as $relative => $checksum) {
    $manifestLines[] = $checksum . '  ' . $relative;
}
file_put_contents($moduleRoot . '/MANIFEST.sha256', implode("\n", $manifestLines) . "\n");

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST,
);
foreach ($iterator as $item) {
    touch($item->getPathname(), $epoch);
}
touch($moduleRoot, $epoch);

$zipPath = $distRoot . '/qrkshipping-' . ModuleMetadata::VERSION . '.zip';
if (is_file($zipPath) && !unlink($zipPath)) {
    throw new RuntimeException('Cannot replace existing release ZIP.');
}
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Cannot create release ZIP.');
}

$zipFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if ($file instanceof SplFileInfo && $file->isFile()) {
        $relative = 'qrkshipping/' . str_replace('\\', '/', substr($file->getPathname(), strlen($moduleRoot) + 1));
        $zipFiles[$relative] = $file->getPathname();
    }
}
ksort($zipFiles);
foreach ($zipFiles as $relative => $absolute) {
    if (!$zip->addFile($absolute, $relative)) {
        throw new RuntimeException('Cannot add file to release ZIP: ' . $relative);
    }
    if (method_exists($zip, 'setMtimeName')) {
        $zip->setMtimeName($relative, $epoch);
    }
}
$zip->close();

$checksum = hash_file('sha256', $zipPath);
file_put_contents($zipPath . '.sha256', $checksum . '  ' . basename($zipPath) . "\n");
file_put_contents($distRoot . '/BUILD_REPORT.json', json_encode([
    'module' => ModuleMetadata::NAME,
    'version' => ModuleMetadata::VERSION,
    'zip' => basename($zipPath),
    'sha256' => $checksum,
    'file_count' => count($zipFiles),
    'source_date_epoch' => $epoch,
    'autoload' => 'deterministic_minimal_psr4',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");

fwrite(STDOUT, $scanOutput);
fwrite(STDOUT, sprintf("Release ZIP: %s\nSHA-256: %s\n", $zipPath, $checksum));
