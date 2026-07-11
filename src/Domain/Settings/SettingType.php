<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Settings;

enum SettingType: string
{
    case STRING = 'string';
    case BOOLEAN = 'boolean';
    case INTEGER = 'integer';
    case DECIMAL = 'decimal';
}
