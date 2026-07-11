# Validation matrix

Last updated: 2026-07-11.

| Gate | Environment | Status |
|---|---|---|
| ZIP handoff integrity | supplied bundle | passed before implementation |
| PHP syntax | local PHP 8.4.16, 115 PHP files | passed |
| Architecture boundary scan | local PHP 8.4.16 | passed |
| Source artifact scan | local PHP 8.4.16, 152 files | passed |
| Framework-independent smoke suite | local PHP 8.4.16, 24 assertions | passed |
| JSON/YAML/XML/XLF portable parse | local Python parsers | passed |
| Composer validation/autoload | Composer environment | not run locally; CI configured |
| Unit/property tests | PHPUnit environment | not run locally; CI configured |
| PHPStan level 8 | PHPStan environment | not run locally; CI configured |
| PHPCS PSR-12 | PHPCS environment | not run locally; CI configured |
| PrestaShop coding-standard report | official `prestashop/php-dev-tools` | not run locally; CI configured |
| MariaDB 10.6 | real database | not run locally; CI configured |
| MariaDB 10.11.9 | real database | not run locally; CI configured |
| MySQL 8.0 | real database | not run locally; CI configured |
| PrestaShop 9.1.4 / PHP 8.2.32 / MariaDB 10.6 | real shop | not run locally; CI configured |
| PrestaShop 9.1.4 / PHP 8.3 / MariaDB 10.11.9 | real shop | not run locally; CI configured |
| PrestaShop 9.1.4 / PHP 8.4 / MySQL 8.0 | real shop | not run locally; CI configured |
| Latest PrestaShop 9.1.x | real shop | 9.1.4 is the release-time target; runtime evidence pending |
| Reproducible release ZIP and checksum | Composer/ZipArchive environment | not run locally; CI configured |

The runtime harness covers install, schema verification, disabled/enabled state, inert cost methods, no carrier,
Back Office tabs and authorization roles, Symfony routes/services, Twig/XLIFF lint, authenticated encryption,
PrestaShop multistore-context mapping, uninstall secret deletion, retained non-secret data and reinstall adoption.

A static pass must never be represented as a database or PrestaShop runtime pass.
