<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Audit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Domain\Audit\AuditActor;

final class AuditActorTest extends TestCase
{
    public function testEmployeeActorRequiresPositiveIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AuditActor::employee(0);
    }

    public function testSystemActorUsesZeroIdentifier(): void
    {
        $actor = AuditActor::system();

        self::assertSame('system', $actor->type());
        self::assertSame(0, $actor->id());
    }
}
