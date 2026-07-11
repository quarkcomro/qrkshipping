<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Port\Clock;

use DateTimeImmutable;

interface ClockPort
{
    public function now(): DateTimeImmutable;
}
