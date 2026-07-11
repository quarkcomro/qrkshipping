<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Support;

use DateTimeImmutable;
use Qrk\Commerce\Shipping\Port\Clock\ClockPort;

final readonly class FrozenClock implements ClockPort
{
    public function __construct(
        private DateTimeImmutable $now,
    ) {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
