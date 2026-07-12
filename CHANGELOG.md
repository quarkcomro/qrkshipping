# Changelog

## [0.1.2] - 2026-07-12

### Added

- Global lifecycle controls in Preferences, editable only in the **All stores** context.
- Optional destructive uninstall policy that removes every table and row in the module-owned `<database-prefix>qrkship_` namespace.
- Optional reset-to-defaults policy that recreates the foundation schema and restores catalog defaults during module Reset.
- Unit, database and PrestaShop runtime scenarios for retained-data reset, destructive reset, retained-data uninstall and destructive uninstall.

### Fixed

- Bypass the PrestaShop SQL result cache for QRK Shipping authoritative reads, including `INFORMATION_SCHEMA`, so Diagnostics observes schema changes immediately without a manual global cache clear.
- Register the PrestaShop reset lifecycle hook so Back Office Reset applies the approved global reset policy.

## [0.1.1] - 2026-07-11

### Fixed

- Accept PrestaShop databases whose default charset/collation is legacy `utf8`/`utf8mb3` when the server supports portable `utf8mb4` collations.
- Create and validate all QRK Shipping tables explicitly as InnoDB/utf8mb4 without altering the shop database defaults.
- Add unit and real-database regression coverage for the reported clean-stage installation failure.

## [0.1.0] - 2026-07-11

### Added

- Greenfield PrestaShop module skeleton and strict runtime guards.
- Provider-neutral Domain/Application/Ports/Adapters separation.
- Five-table foundation schema with defensive install, rollback and retained-data uninstall policy.
- Typed settings with `all -> shop group -> shop` inheritance.
- Authenticated secret storage contract and AES-256-GCM implementation.
- Provider-account draft shell scoped explicitly to a shop.
- Exact decimal, money, currency and exchange-rate snapshot value objects.
- Symfony/Twig Dashboard, Preferences, Help and Diagnostics shells.
- RO/EN XLF translation foundations.
- Automated static, architecture, unit and artifact gates.
- Real MariaDB/MySQL integration suites and a PrestaShop 9.1.4 lifecycle runtime harness.
- Accessibility contract checks for the Back Office templates.
