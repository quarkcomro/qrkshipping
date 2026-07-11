<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Provider;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Application\Provider\ProviderAccountService;
use Qrk\Commerce\Shipping\Domain\Audit\AuditActor;
use Qrk\Commerce\Shipping\Domain\Provider\ProviderAccountStatus;
use Qrk\Commerce\Shipping\Domain\Provider\ProviderCode;
use Qrk\Commerce\Shipping\Tests\Support\FrozenClock;
use Qrk\Commerce\Shipping\Tests\Support\InMemoryAuditEventRepository;
use Qrk\Commerce\Shipping\Tests\Support\InMemoryProviderAccountRepository;
use Qrk\Commerce\Shipping\Tests\Support\InMemoryTransactionManager;

final class ProviderAccountServiceTest extends TestCase
{
    public function testDraftAccountsAreIsolatedPerShop(): void
    {
        $repository = new InMemoryProviderAccountRepository();
        $audit = new InMemoryAuditEventRepository();
        $service = new ProviderAccountService(
            $repository,
            $audit,
            new InMemoryTransactionManager(),
            new FrozenClock(new DateTimeImmutable('2026-07-11T12:00:00+00:00', new DateTimeZone('UTC'))),
        );
        $provider = ProviderCode::fromString('cargus');

        $first = $service->saveDraftLabel($provider, 10, 2, ' Cargus primary ', AuditActor::employee(1));
        $second = $service->saveDraftLabel($provider, 11, 2, 'Cargus secondary', AuditActor::employee(1));

        self::assertNotSame($first->id(), $second->id());
        self::assertSame('Cargus primary', $service->find($provider, 10)?->label());
        self::assertSame('Cargus secondary', $service->find($provider, 11)?->label());
        self::assertSame(ProviderAccountStatus::DRAFT, $first->status());
        self::assertCount(2, $audit->events);
    }


    public function testInvalidShopGroupIsRejectedBeforePersistence(): void
    {
        $repository = new InMemoryProviderAccountRepository();
        $service = new ProviderAccountService(
            $repository,
            new InMemoryAuditEventRepository(),
            new InMemoryTransactionManager(),
            new FrozenClock(new DateTimeImmutable('2026-07-11T12:00:00+00:00')),
        );

        try {
            $service->saveDraftLabel(
                ProviderCode::fromString('cargus'),
                10,
                0,
                'Invalid scope',
                AuditActor::employee(1),
            );
            self::fail('Invalid shop-group context was accepted.');
        } catch (InvalidArgumentException) {
            self::assertNull($repository->find(ProviderCode::fromString('cargus'), 10));
        }
    }

    public function testRejectsNonShopAccountLookup(): void
    {
        $service = new ProviderAccountService(
            new InMemoryProviderAccountRepository(),
            new InMemoryAuditEventRepository(),
            new InMemoryTransactionManager(),
            new FrozenClock(new DateTimeImmutable('2026-07-11T12:00:00+00:00')),
        );

        $this->expectException(InvalidArgumentException::class);
        $service->find(ProviderCode::fromString('cargus'), 0);
    }
}
