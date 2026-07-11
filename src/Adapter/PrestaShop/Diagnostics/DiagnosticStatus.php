<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Diagnostics;

enum DiagnosticStatus: string
{
    case PASS = 'pass';
    case WARNING = 'warning';
    case FAIL = 'fail';
}
