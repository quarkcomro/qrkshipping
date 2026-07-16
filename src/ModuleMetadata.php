<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping;

final class ModuleMetadata
{
    public const NAME = 'qrkshipping';
    public const VERSION = '0.1.3';
    public const MIN_PRESTASHOP_VERSION = '9.1.4';
    public const MAX_PRESTASHOP_VERSION_EXCLUSIVE = '10.0.0';
    public const MIN_PHP_VERSION_ID = 80232;
    public const MIN_MARIADB_VERSION = '10.6.0';
    public const MIN_MYSQL_VERSION = '8.0.0';
    public const INITIAL_PROVIDER_CODE = 'cargus';
    public const SCHEMA_VERSION = '0001_foundation';

    private function __construct()
    {
    }
}
