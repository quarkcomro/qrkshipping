<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Money;

enum RoundingMode: string
{
    case HALF_UP = 'half_up';
    case HALF_EVEN = 'half_even';
    case DOWN = 'down';
    case UP = 'up';
}
