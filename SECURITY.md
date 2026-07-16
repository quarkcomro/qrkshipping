# Security policy

## Supported line

The active development line targets PrestaShop >= 9.1.4 and < 10.0 with PHP >= 8.2.32.

## Reporting

Report suspected vulnerabilities privately to the project owner. Do not include live credentials, tokens, customer data or unredacted logs.

## Foundation guarantees

- Secrets are encrypted using authenticated encryption and are never intentionally logged.
- Empty credential fields preserve existing values; deletion is an explicit operation.
- Back Office mutations require permission checks, POST and CSRF validation.
- No executable asset is loaded from a CDN.
- No provider HTTP request exists in Increment 1.
- Encrypted secret rows are always removed during uninstall and Reset.
- Destructive lifecycle behavior is disabled by default and stored globally. With two or more configured stores it is editable only in the All stores context; with one configured store, an administrator authorized for all stores may edit it from the current store context.
- Destructive uninstall removes only tables in the module-owned `<database-prefix>qrkship_` namespace; returned identifiers are validated before they are quoted and dropped.
