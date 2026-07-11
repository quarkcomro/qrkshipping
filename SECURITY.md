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
- Uninstall removes encrypted secret rows while retaining non-secret foundation data.
