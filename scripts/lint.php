<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$excludedSegments = ['/.git/', '/vendor/', '/build/', '/dist/', '/.phpstan.cache/', '/.phpunit.cache/'];
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = str_replace('\\', '/', $file->getPathname());
    $excluded = false;
    foreach ($excludedSegments as $segment) {
        if (str_contains($path, $segment)) {
            $excluded = true;
            break;
        }
    }
    if (!$excluded) {
        $files[] = $file->getPathname();
    }
}

sort($files);
$failures = [];
foreach ($files as $file) {
    $command = [PHP_BINARY, '-l', $file];
    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptorSpec, $pipes);
    if (!is_resource($process)) {
        $failures[] = $file . ': could not start PHP lint';
        continue;
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0) {
        $failures[] = trim($stdout . "\n" . $stderr);
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf("PHP syntax: %d files passed.\n", count($files)));
