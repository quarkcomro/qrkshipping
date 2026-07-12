<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Application\Lifecycle;

enum LifecycleOperation: string
{
    case UNINSTALL = 'uninstall';
    case RESET = 'reset';
}
