# QRK Shipping

Greenfield shipping module for **PrestaShop >= 9.1.4 and < 10.0**.

The first increment is deliberately operationally inert:

- provider-neutral Core;
- Cargus provider-account shell only;
- no live Cargus HTTP;
- no carrier creation;
- no checkout participation;
- no AWB, label, tracking, PUDO, synchronization, or commercial pricing.

## Runtime baseline

- PHP >= 8.2.32
- Symfony 6.4 supplied by PrestaShop
- MariaDB >= 10.6 or MySQL >= 8.0
- InnoDB support; QRK Shipping tables are created explicitly as `utf8mb4` (the database default may remain `utf8`/`utf8mb3`)

## Development

```bash
composer install
composer quality
composer build
```

`composer build` runs the local build script, which creates a reproducible module ZIP under `dist/` with a deterministic module-only PSR-4 autoloader. Symfony and PrestaShop are never bundled by this module.

## Validation status

The repository contains automated unit, architecture, schema-planning, lint,
static-analysis, real-database, PrestaShop lifecycle and artifact-scan gates.
The runtime matrix deliberately pairs PHP 8.2.32, 8.3 and 8.4 with MariaDB 10.6,
MariaDB 10.11.9 and MySQL 8.0. A runtime claim is valid only after the relevant
GitHub Actions job has completed successfully; see `docs/reports/VALIDATION_MATRIX.md`.
