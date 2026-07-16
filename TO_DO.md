# TO DO

## Required before Increment 1 acceptance

- Obtain successful CI evidence for Composer validation, PHPUnit, PHPStan, PHPCS and official PrestaShop formatting.
- Obtain successful real-database evidence on MariaDB 10.6, MariaDB 10.11.9 and MySQL 8.0.
- Obtain successful PrestaShop 9.1.4 lifecycle evidence on PHP 8.2.32, 8.3 and 8.4.
- Execute in-place PrestaShop runtime upgrades `0.1.1 -> 0.1.2 -> 0.1.3` and verify reset-hook registration plus single-shop global-policy editing.
- Record the reproducible release ZIP SHA-256 produced after all required jobs pass.
- Perform an authenticated browser review of the four Back Office pages before release acceptance; the current
  automated runtime harness validates routing, service compilation, permissions/CSRF contracts, templates and
  lifecycle without claiming a human browser review.
- Re-test both lifecycle controls through the authenticated Back Office UI in a one-shop topology and in an All stores context with at least two shops; confirm both destructive paths.

## Explicitly outside this increment

- Cargus HTTP/authentication and synchronization.
- Carrier lifecycle and checkout.
- Commercial pricing and currency conversion.
- PUDO, AWB, labels, cancellation and tracking.
- Legacy-module migration or backward compatibility.
