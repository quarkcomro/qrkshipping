<?php

declare(strict_types=1);

$target = $argv[1] ?? dirname(__DIR__);
$mode = in_array('--release', $argv, true) ? 'release' : 'source';
$target = realpath($target) ?: $target;
$errors = [];
$files = [];
$excluded = $mode === 'source'
    ? ['/.git/', '/vendor/', '/build/', '/dist/', '/.phpstan.cache/', '/.phpunit.cache/']
    : [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    $skip = false;
    foreach ($excluded as $segment) {
        if (str_contains($path, $segment)) {
            $skip = true;
            break;
        }
    }
    if (!$skip) {
        $files[] = $path;
    }
}

$archiveExtensions = ['zip', 'tar', 'tgz', 'gz', 'bz2', '7z', 'rar'];
$temporaryPatterns = ['~', '.swp', '.swo', '.tmp', '.bak', '.orig', '.rej'];
foreach ($files as $file) {
    $relative = ltrim(str_replace(str_replace('\\', '/', $target), '', $file), '/');
    $lower = strtolower($relative);
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (in_array($extension, $archiveExtensions, true)) {
        $errors[] = $relative . ': nested archive is forbidden';
    }
    if (preg_match('/(?:^|\\/)(?:\.env(?:\..*)?|id_rsa|id_ed25519|.*\.pem|.*\.key|.*\.log)$/i', $relative) === 1) {
        $errors[] = $relative . ': secret or runtime file is forbidden';
    }
    foreach ($temporaryPatterns as $suffix) {
        if (str_ends_with($lower, strtolower($suffix))) {
            $errors[] = $relative . ': temporary file is forbidden';
        }
    }

    if (in_array($extension, ['php', 'twig', 'html', 'yml', 'yaml', 'xml', 'xlf', 'json', 'md'], true)) {
        $content = (string) file_get_contents($file);
        $unsafePhpCall = '/\\b(?:var_dump|print_r|dd|dump|eval|shell_exec|passthru|proc_nice|unserialize)'
            . '\\s*\\(/i';
        if ($extension === 'php' && preg_match($unsafePhpCall, $content) === 1) {
            $errors[] = $relative . ': forbidden debug or unsafe PHP call';
        }
        if (in_array($extension, ['twig', 'html'], true)
            && preg_match('/<(?:script|link|img)\\b[^>]*(?:src|href)=["\']https?:\\/\\//i', $content) === 1
        ) {
            $errors[] = $relative . ': remote asset is forbidden';
        }
        if (str_contains($content, '-----BEGIN ' . 'PRIVATE KEY-----')) {
            $errors[] = $relative . ': private key material detected';
        }
    }
}

if ($mode === 'release') {
    foreach (['tests/', 'scripts/', 'stubs/', '.github/', 'docs/'] as $forbiddenDirectory) {
        foreach ($files as $file) {
            $relative = ltrim(str_replace(str_replace('\\', '/', $target), '', $file), '/');
            if (str_starts_with($relative, $forbiddenDirectory)) {
                $errors[] = $relative . ': development directory leaked into release';
            }
        }
    }

    foreach ($files as $file) {
        $relative = ltrim(str_replace(str_replace('\\', '/', $target), '', $file), '/');
        if (str_starts_with($relative, 'vendor/symfony/') || str_starts_with($relative, 'vendor/twig/')) {
            $errors[] = $relative . ': platform dependency was bundled';
        }
    }
}

if ($errors !== []) {
    $errors = array_values(array_unique($errors));
    sort($errors);
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Artifact scan (%s): %d files passed.\n", $mode, count($files)));
