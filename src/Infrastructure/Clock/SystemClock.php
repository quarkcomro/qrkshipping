<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Clock;

use DateTimeImmutable;
use DateTimeZone;
use Qrk\Commerce\Shipping\Port\Clock\ClockPort;

final class SystemClock implements ClockPort
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
