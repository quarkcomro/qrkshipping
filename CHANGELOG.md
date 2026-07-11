# Changelog

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
