<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Settings;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Application\Settings\SettingValueCodec;
use Qrk\Commerce\Shipping\Application\Settings\SettingsCatalog;
use Qrk\Commerce\Shipping\Application\Settings\SettingsService;
use Qrk\Commerce\Shipping\Domain\Audit\AuditActor;
use Qrk\Commerce\Shipping\Domain\Settings\SettingDefinition;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Domain\Settings\SettingType;
use Qrk\Commerce\Shipping\Tests\Support\FrozenClock;
use Qrk\Commerce\Shipping\Tests\Support\InMemoryAuditEventRepository;
use Qrk\Commerce\Shipping\Tests\Support\InMemorySettingRepository;
use Qrk\Commerce\Shipping\Tests\Support\InMemoryTransactionManager;

final class SettingsServiceTest extends TestCase
{
    public function testResolvesShopThenGroupThenAllThenDefault(): void
    {
        [$service, $repository] = $this->service();
        $actor = AuditActor::employee(7);
        $all = SettingScope::all();
        $group = SettingScope::shopGroup(3);
        $shop = SettingScope::shop(9, 3);

        self::assertSame('default', $service->resolve('test.string', $shop)->value());

        $service->set('test.string', $all, 'all', $actor);
        self::assertSame('all', $service->resolve('test.string', $shop)->value());
        self::assertTrue($service->resolve('test.string', $shop)->sourceScope()?->equals($all));

        $service->set('test.string', $group, 'group', $actor);
        self::assertSame('group', $service->resolve('test.string', $shop)->value());

        $service->set('test.string', $shop, 'shop', $actor);
        self::assertSame('shop', $service->resolve('test.string', $shop)->value());

        $service->removeOverride('test.string', $shop, $actor);
        self::assertSame('group', $service->resolve('test.string', $shop)->value());
        self::assertGreaterThan(0, $repository->findCalls);
    }

    public function testExplicitEmptyIsDifferentFromMissing(): void
    {
        [$service] = $this->service();
        $scope = SettingScope::shop(4, 2);
        $actor = AuditActor::employee(1);

        self::assertSame('fallback', $service->resolve('test.empty', $scope)->value());
        $service->set('test.empty', $scope, '', $actor);

        $resolved = $service->resolve('test.empty', $scope);
        self::assertSame('', $resolved->value());
        self::assertTrue($resolved->isExplicitEmpty());
        self::assertFalse($resolved->usesDefault());
    }

    public function testCacheDoesNotMixShopsAndIsInvalidatedOnWrite(): void
    {
        [$service, $repository] = $this->service();
        $actor = AuditActor::employee(2);
        $shopA = SettingScope::shop(10, 5);
        $shopB = SettingScope::shop(11, 5);

        $service->set('test.string', $shopA, 'A', $actor);
        $service->set('test.string', $shopB, 'B', $actor);
        self::assertSame('A', $service->resolve('test.string', $shopA)->value());
        self::assertSame('B', $service->resolve('test.string', $shopB)->value());

        $callsAfterFirstRead = $repository->findCalls;
        self::assertSame('A', $service->resolve('test.string', $shopA)->value());
        self::assertSame($callsAfterFirstRead, $repository->findCalls);

        $service->set('test.string', $shopA, 'A2', $actor);
        self::assertSame('A2', $service->resolve('test.string', $shopA)->value());
    }

    public function testAuditNeverContainsTheSettingValue(): void
    {
        [$service, , $audit] = $this->service();
        $service->set('test.string', SettingScope::all(), 'sensitive-looking-value', AuditActor::employee(4));

        self::assertCount(1, $audit->events);
        self::assertStringNotContainsString('sensitive-looking-value', $audit->events[0]->metadataJson());
    }

    /**
     * @return array{SettingsService, InMemorySettingRepository, InMemoryAuditEventRepository}
     */
    private function service(): array
    {
        $repository = new InMemorySettingRepository();
        $audit = new InMemoryAuditEventRepository();
        $catalog = new SettingsCatalog([
            new SettingDefinition('test.string', SettingType::STRING, 'default'),
            new SettingDefinition('test.empty', SettingType::STRING, 'fallback', true),
            new SettingDefinition('test.boolean', SettingType::BOOLEAN, '0'),
            new SettingDefinition('test.integer', SettingType::INTEGER, '1'),
            new SettingDefinition('test.decimal', SettingType::DECIMAL, '1.25'),
        ]);
        $service = new SettingsService(
            $catalog,
            new SettingValueCodec(),
            $repository,
            $audit,
            new InMemoryTransactionManager(),
            new FrozenClock(new DateTimeImmutable('2026-07-11T12:00:00+00:00', new DateTimeZone('UTC'))),
        );

        return [$service, $repository, $audit];
    }
}
