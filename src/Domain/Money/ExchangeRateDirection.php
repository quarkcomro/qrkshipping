<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Money;

enum ExchangeRateDirection: string
{
    case SOURCE_TO_TARGET = 'source_to_target';
    case TARGET_TO_SOURCE = 'target_to_source';
}
