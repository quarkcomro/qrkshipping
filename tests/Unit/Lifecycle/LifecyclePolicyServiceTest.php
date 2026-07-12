<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Lifecycle;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Application\Lifecycle\LifecycleOperation;
use Qrk\Commerce\Shipping\Application\Lifecycle\LifecyclePolicy;
use Qrk\Commerce\Shipping\Application\Lifecycle\LifecyclePolicyService;
use Qrk\Commerce\Shipping\Application\Settings\SettingValueCodec;
use Qrk\Commerce\Shipping\Application\Settings\SettingsCatalog;
use Qrk\Commerce\Shipping\Application\Settings\SettingsService;
use Qrk\Commerce\Shipping\Domain\Audit\AuditActor;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Tests\Support\FrozenClock;
use Qrk\Commerce\Shipping\Tests\Support\InMemoryAuditEventRepository;
use Qrk\Commerce\Shipping\Tests\Support\InMemorySettingRepository;
use Qrk\Commerce\Shipping\Tests\Support\InMemoryTransactionManager;

final class LifecyclePolicyServiceTest extends TestCase
{
    public function testDefaultsAreNonDestructive(): void
    {
        [$service] = $this->service();

        $policy = $service->current();

        self::assertFalse($policy->purgeOnUninstall());
        self::assertFalse($policy->resetToDefaults());
    }

    public function testOperationPoliciesRemainIndependent(): void
    {
        $uninstallOnly = new LifecyclePolicy(true, false);
        self::assertTrue($uninstallOnly->purgeFor(LifecycleOperation::UNINSTALL));
        self::assertFalse($uninstallOnly->purgeFor(LifecycleOperation::RESET));

        $resetOnly = new LifecyclePolicy(false, true);
        self::assertFalse($resetOnly->purgeFor(LifecycleOperation::UNINSTALL));
        self::assertTrue($resetOnly->purgeFor(LifecycleOperation::RESET));
    }

    public function testSavesBothGlobalFlagsAtomically(): void
    {
        [$service, $settingsRepository, $audit, $transactions] = $this->service();

        $service->save(true, true, AuditActor::employee(7));
        $policy = $service->current();

        self::assertTrue($policy->purgeOnUninstall());
        self::assertTrue($policy->resetToDefaults());
        self::assertSame(1, $transactions->runs);
        self::assertCount(2, $audit->events);
        self::assertSame($audit->events[0]->correlationId(), $audit->events[1]->correlationId());
        self::assertTrue($audit->events[0]->scope()->equals(SettingScope::all()));
        self::assertTrue($audit->events[1]->scope()->equals(SettingScope::all()));
        self::assertNotNull($settingsRepository->find(
            SettingsCatalog::LIFECYCLE_PURGE_ON_UNINSTALL,
            SettingScope::all(),
        ));
        self::assertNotNull($settingsRepository->find(
            SettingsCatalog::LIFECYCLE_RESET_TO_DEFAULTS,
            SettingScope::all(),
        ));
    }

    /**
     * @return array{
     *   LifecyclePolicyService,
     *   InMemorySettingRepository,
     *   InMemoryAuditEventRepository,
     *   InMemoryTransactionManager
     * }
     */
    private function service(): array
    {
        $repository = new InMemorySettingRepository();
        $audit = new InMemoryAuditEventRepository();
        $transactions = new InMemoryTransactionManager();
        $settings = new SettingsService(
            new SettingsCatalog(),
            new SettingValueCodec(),
            $repository,
            $audit,
            $transactions,
            new FrozenClock(new DateTimeImmutable('2026-07-12T12:00:00+00:00')),
        );

        return [new LifecyclePolicyService($settings), $repository, $audit, $transactions];
    }
}
