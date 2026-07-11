# Validation matrix

Last updated: 2026-07-11 for the `0.1.1` corrective candidate.

| Gate | Environment | Status |
|---|---|---|
| Reported clean-stage failure reproduced by source inspection | `0.1.0` release | passed: install guard rejected database defaults `utf8`/`utf8mb3` before schema creation |
| PHP syntax | local PHP 8.4.16, 119 PHP files | passed |
| JSON, YAML and XML/XLF well-formed parsing | local Python parsers | passed |
| Canonical PHP configuration validator | local PHP 8.4.16 | not run: local PHP DOM extension unavailable |
| Architecture boundary scan | local PHP 8.4.16 | passed |
| Source artifact scan | local PHP 8.4.16, 157 files | passed |
| Framework-independent smoke suite | local PHP 8.4.16, 26 assertions | passed, including `utf8mb3` default fallback and fail-closed absence of portable `utf8mb4` |
| PHPUnit unit/property tests | dependency environment | not run locally; regression tests added |
| PHPStan level 8 | dependency environment | not run locally |
| PHPCS PSR-12 | dependency environment | not run locally |
| PrestaShop coding-standard report | official `prestashop/php-dev-tools` | not run locally |
| Canonical `composer build` | local PHP 8.4.16 | not completed: local PHP ZipArchive extension unavailable |
| Deterministic fallback ZIP packaging | local Python 3 | passed twice with identical SHA-256 |
| Release ZIP structural test | system `unzip` | passed, 112 files |
| Release manifest checksum verification | local `sha256sum -c` | passed |
| Release artifact scan | extracted `0.1.1` candidate | passed, 112 files |
| MariaDB 10.6 regression: database default `utf8`/`utf8mb3` | real database | not run locally; CI test added |
| MariaDB 10.11.9 regression: database default `utf8`/`utf8mb3` | real database | not run locally; CI test added |
| MySQL 8.0 regression: database default `utf8`/`utf8mb3` | real database | not run locally; CI test added |
| PrestaShop 9.1.4 install on reported stage class | real shop | not run by the assistant; requires stage retest |
| Disable/enable/uninstall/reinstall lifecycle | real shop | not run for `0.1.1` |

The corrective implementation no longer treats the database default charset or collation as a blocking requirement.
It requires the server to expose a portable `utf8mb4` collation and creates every module table explicitly as
`ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`. Existing module tables remain subject to the strict per-table
InnoDB/utf8mb4 compatibility checks.

Static or simulated validation must not be represented as a real MariaDB, MySQL or PrestaShop runtime pass.
