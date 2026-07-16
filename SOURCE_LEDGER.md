# Source ledger

This repository is greenfield. Donor packages are not code bases.

| New component | Source consulted | Decision | Difference / test evidence |
|---|---|---|---|
| Layer boundaries and thin entrypoint | `qrkshipping_v1.0.0.11` architecture | adapted | Recreated with a five-table schema and no alpha upgrade history; architecture gate included. |
| Multistore setting precedence | `qrkshipping_v1.0.0.11` settings model | adapted | Explicit `all -> shop_group -> shop`, typed absence/empty semantics and cache-isolation tests. |
| Authenticated secret envelope | `qrkshipping_v1.0.0.11` secret-store pattern | adapted | Reimplemented AES-256-GCM with versioned AAD, key IDs, tamper tests and no donor file copied. |
| Schema rollback/fingerprint pattern | `qrkshipping_v1.0.0.11` installer concepts | adapted | Reduced to the approved five tables; partial schemas fail closed; rollback tracks only current-run objects. |
| Carrier cost methods return `false` | PrestaShop `CarrierModule` contract and donor negative behavior | adopted as invariant | No carrier is created and no DB/HTTP call exists in cost methods. |
| Cargus API behavior | `cargus_ps91_v1.0.26` | deferred | No Cargus API code or fixture enters Increment 1. |
| Lifecycle purge/reset policy | Approved project decision and PrestaShop 9.1.4 module lifecycle contract | new implementation | Global typed settings, All-stores mutation when two or more shops exist, authorized single-shop fallback when the selector is unavailable, reset-hook upgrade path, destructive-operation warnings and lifecycle tests. |
| Authoritative metadata reads | PrestaShop 9.1.4 `Db::executeS` cache contract | adapted | QRK Shipping reads bypass the platform SQL result cache; regression test verifies `use_cache=false`. |

No donor production source file has been copied verbatim into this increment.
