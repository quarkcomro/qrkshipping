# Validation matrix

Last updated: 2026-07-16 for the `0.1.3` corrective candidate.

## Local source and artifact evidence

| Gate | Environment | Status |
|---|---|---|
| PHP syntax | local PHP 8.4.16 | passed, 135 production/test/script files through the canonical lint script |
| JSON parsing | local Python 3 | passed, 2 files |
| YAML parsing | local Python 3 / PyYAML 6.0.3 | passed, routes, services and GitHub Actions workflow |
| XML/XLF well-formed parsing | local Python 3 / ElementTree | passed, 13 files |
| RO/EN translation source parity | local Python 3 | passed for 6 translation domains |
| Twig structural validation | local Python 3 | passed, 6 templates |
| Canonical PHP configuration validator | local PHP 8.4.16 | not run: local PHP DOM extension unavailable |
| Composer validation and dependency installation | Composer environment | not run locally: Composer and outbound package resolution unavailable |
| Architecture boundary scan | local PHP 8.4.16 | passed |
| Source artifact scan | local PHP 8.4.16, 173 files | passed |
| Framework-independent smoke suite | local PHP 8.4.16 | passed, 48 assertions including single-shop lifecycle access, multi-shop denial, authorization denial, upgrade entrypoints and SQL-cache bypass |
| Schema manifest version/fingerprint/checksum | local PHP 8.4.16 | passed; fingerprint `095104f966f4f204936c186fec66a578241e91da26e720861f554313eddd0723` |
| PHPUnit unit/property tests | dependency environment | not run locally; new tests added for lifecycle-policy access, shop topology and the `0.1.3` upgrade entrypoint |
| PHPStan level 8 | dependency environment | not run locally |
| PHPCS PSR-12 | dependency environment | not run locally |
| PrestaShop coding-standard report | official `prestashop/php-dev-tools` | not run locally |
| Canonical PHP build staging | local PHP 8.4.16 | passed through release staging, module-only autoloader, manifest and release scan; ZIP creation stopped because local PHP ZipArchive is unavailable |
| Deterministic fallback ZIP packaging | local Python 3 | passed twice with identical bytes |
| Release ZIP structural test | system `unzip` | passed, 120 files and 40 directory entries |
| Release manifest checksum verification | local `sha256sum -c` | passed |
| Release artifact scan | freshly extracted `0.1.3` ZIP | passed, 120 files |
| Release entrypoint smoke | freshly staged `0.1.3` payload with PrestaShop stubs | passed; version and inert cost methods verified |
| Source/release entrypoint equality | byte comparison | passed |
| Release SHA-256 | deterministic fallback ZIP | `98b528d2a9b74e7519aba621216e377d7d3f809340d02b77becf9fbf4d94a600` |

## User-executed stage evidence

### `0.1.1`

The project owner executed the following observations on a clean PrestaShop 9.1.4 stage:

| Scenario | Status |
|---|---|
| Module upload | passed |
| Install with database default `utf8mb3` | passed |
| Configure and all four Symfony Back Office pages | passed |
| Five approved `qrkship_` tables physically present | passed |
| Diagnostics immediately after install | initially false-negative: cached `schema.incomplete` with zero detected tables |
| Diagnostics after global PrestaShop cache clear | passed: `schema.compatible` |
| Disable and re-enable | passed |
| Default Reset data retention | passed: saved diagnostic detail level remained |
| Default uninstall retention | passed: all five tables remained |
| Reinstall through compatible-schema adoption | passed |

### `0.1.2`

The project owner confirmed that the new lifecycle controls were inaccessible when multistore was enabled but only one
shop group and one shop existed. PrestaShop did not render the All stores selector, while the module required that
unreachable context. This is the runtime defect addressed by `0.1.3`.

## Required runtime matrix for `0.1.3`

| Gate | Environment | Status |
|---|---|---|
| MariaDB schema/lifecycle suite | MariaDB 10.6 | not run locally; CI configured |
| MariaDB schema/lifecycle suite | MariaDB 10.11.9 | not run locally; CI configured |
| MySQL schema/lifecycle suite | MySQL 8.0 | not run locally; CI configured |
| PrestaShop runtime | PS 9.1.4 / PHP 8.2.32 / MariaDB 10.6 | not run; CI configured |
| PrestaShop runtime | PS 9.1.4 / PHP 8.3 / MariaDB 10.11.9 | not run; CI configured |
| PrestaShop runtime | PS 9.1.4 / PHP 8.4 / MySQL 8.0 | not run; CI configured |
| Upgrade in place | `0.1.1 -> 0.1.2 -> 0.1.3` | not run; explicit upgrade entrypoints and local smoke coverage exist |
| One configured shop, shop context, employee authorized for all shops | real authenticated BO | not run for `0.1.3`; expected editable global lifecycle form with single-shop notice |
| Two or more configured shops, shop context | real authenticated BO | not run; expected read-only policy and required All stores context |
| Two or more configured shops, All stores context | real authenticated BO | not run; expected editable policy |
| Employee without authorization for all shops | real authenticated BO | not run; expected read-only policy |
| Default Reset retention | real PS 9.1.4 | not run for `0.1.3`; CI configured |
| Reset-to-defaults while module is disabled | real PS 9.1.4 | not run; CI configured |
| Default uninstall retention and mandatory secret deletion | real PS 9.1.4 | not run for `0.1.3`; CI configured |
| Explicit full purge on uninstall, including an extra `qrkship_` namespace probe table | real PS 9.1.4 | not run; CI configured |
| Reinstall after full purge | real PS 9.1.4 | not run; CI configured |

Static, simulated, user-reported and CI evidence must remain separate. An unexecuted database or PrestaShop runtime
combination must not be represented as passed.
