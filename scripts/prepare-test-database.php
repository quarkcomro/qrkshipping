<?php

declare(strict_types=1);

$dsn = getenv('QRK_DB_ADMIN_DSN');
$user = getenv('QRK_DB_USER');
$password = getenv('QRK_DB_PASSWORD');
$database = getenv('QRK_DB_NAME');
$charset = getenv('QRK_DB_CHARSET');
$collation = getenv('QRK_DB_COLLATION');

if (!is_string($dsn) || $dsn === '') {
    throw new RuntimeException('QRK_DB_ADMIN_DSN is required.');
}
if (!is_string($database) || preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $database) !== 1) {
    throw new RuntimeException('QRK_DB_NAME is invalid.');
}

$user = is_string($user) ? $user : '';
$password = is_string($password) ? $password : '';
$charset = is_string($charset) && $charset !== '' ? $charset : 'utf8mb4';
$collation = is_string($collation) && $collation !== '' ? $collation : 'utf8mb4_unicode_ci';
if (preg_match('/^[A-Za-z0-9_]+$/D', $charset) !== 1) {
    throw new RuntimeException('QRK_DB_CHARSET is invalid.');
}
if (preg_match('/^[A-Za-z0-9_]+$/D', $collation) !== 1) {
    throw new RuntimeException('QRK_DB_COLLATION is invalid.');
}
$pdo = null;
$lastFailure = null;

for ($attempt = 1; $attempt <= 60; ++$attempt) {
    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        break;
    } catch (PDOException $exception) {
        $lastFailure = $exception;
        sleep(2);
    }
}

if (!$pdo instanceof PDO) {
    throw new RuntimeException('Database service did not become available.', previous: $lastFailure);
}

$pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $database));
$pdo->exec(sprintf(
    'CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s',
    $database,
    $charset,
    $collation,
));

$version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
fwrite(STDOUT, sprintf(
    "Prepared database %s on server %s with %s/%s.\n",
    $database,
    $version,
    $charset,
    $collation,
));
