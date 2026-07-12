# Validation matrix

Last updated: 2026-07-12 for the `0.1.2` corrective candidate.

## Local source and artifact evidence

| Gate | Environment | Status |
|---|---|---|
| PHP syntax | local PHP 8.4.16, 129 PHP files | passed |
| JSON parsing | local Python 3 | passed, 2 files |
| YAML parsing | local Python 3 / PyYAML 6.0.3 | passed, routes, services and GitHub Actions workflow |
| XML/XLF well-formed parsing | local Python 3 / ElementTree | passed, 13 files |
| RO/EN translation source parity | local Python 3 | passed for 6 translation domains |
| Canonical PHP configuration validator | local PHP 8.4.16 | not run: local PHP DOM extension unavailable |
| Composer validation and dependency installation | Composer environment | not run locally: Composer and outbound package resolution unavailable |
| Architecture boundary scan | local PHP 8.4.16 | passed |
| Source artifact scan | local PHP 8.4.16, 167 files | passed |
| Framework-independent smoke suite | local PHP 8.4.16, 43 assertions | passed |
| Schema manifest version/fingerprint/checksum | local PHP 8.4.16 | passed; fingerprint `095104f966f4f204936c186fec66a578241e91da26e720861f554313eddd0723` |
| PHPUnit unit/property tests | dependency environment | not run locally; tests added for lifecycle policy, reset detection, purge and SQL-cache bypass |
| PHPStan level 8 | dependency environment | not run locally |
| PHPCS PSR-12 | dependency environment | not run locally |
| PrestaShop coding-standard report | official `prestashop/php-dev-tools` | not run locally |
| Canonical PHP build staging | local PHP 8.4.16 | passed through release staging, manifest and release scan; ZIP creation stopped because local PHP ZipArchive is unavailable |
| Deterministic fallback ZIP packaging | local Python 3 | passed twice with identical bytes |
| Release ZIP structural test | system `unzip` | passed, 117 files and 40 directory entries |
| Release manifest checksum verification | local `sha256sum -c` | passed |
| Release artifact scan | freshly extracted `0.1.2` ZIP | passed, 117 files |
| Release entrypoint smoke | freshly extracted `0.1.2` ZIP with PrestaShop stubs | passed; version and inert cost methods verified |
| Source/release entrypoint equality | byte comparison | passed |
| Release SHA-256 | deterministic fallback ZIP | `cb314c8bab2e53b78774f0499ab20875c71c41271179740151e79c6d270ee1be` |

## User-executed stage evidence for `0.1.1`

The following observations were executed by the project owner on a clean PrestaShop 9.1.4 stage. They are evidence for
`0.1.1`, not a runtime pass for the new `0.1.2` lifecycle controls.

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

The `0.1.2` adapter now bypasses PrestaShop SQL-result caching for authoritative reads, so the reported Diagnostics
false-negative should not require a global cache clear. This correction has static and simulated regression coverage,
but it still requires a real `0.1.2` stage retest.

## Required runtime matrix for `0.1.2`

| Gate | Environment | Status |
|---|---|---|
| MariaDB schema/lifecycle suite | MariaDB 10.6 | not run locally; CI configured |
| MariaDB schema/lifecycle suite | MariaDB 10.11.9 | not run locally; CI configured |
| MySQL schema/lifecycle suite | MySQL 8.0 | not run locally; CI configured |
| PrestaShop runtime | PS 9.1.4 / PHP 8.2.32 / MariaDB 10.6 | not run; CI configured |
| PrestaShop runtime | PS 9.1.4 / PHP 8.3 / MariaDB 10.11.9 | not run; CI configured |
| PrestaShop runtime | PS 9.1.4 / PHP 8.4 / MySQL 8.0 | not run; CI configured |
| Upgrade in place | `0.1.1 -> 0.1.2` | not run; upgrade entrypoint and unit/smoke coverage added |
| Default Reset retention | real PS 9.1.4 | not run for `0.1.2`; CI configured |
| Reset-to-defaults while module is disabled | real PS 9.1.4 | not run; BO/CLI operation detection tests and CI scenario added |
| Default uninstall retention and mandatory secret deletion | real PS 9.1.4 | not run for `0.1.2`; CI configured |
| Explicit full purge on uninstall, including an extra `qrkship_` namespace probe table | real PS 9.1.4 | not run; CI configured |
| Reinstall after full purge | real PS 9.1.4 | not run; CI configured |
| All-stores-only mutation through authenticated BO | real browser session | not run for `0.1.2` |

Static, simulated, user-reported and CI evidence must remain separate. An unexecuted database or PrestaShop runtime
combination must not be represented as passed.
