<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Application\Settings;

use Qrk\Commerce\Shipping\Domain\Audit\AuditActor;
use Qrk\Commerce\Shipping\Domain\Audit\AuditEvent;
use Qrk\Commerce\Shipping\Domain\Settings\ResolvedSetting;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Domain\Settings\StoredSetting;
use Qrk\Commerce\Shipping\Port\Clock\ClockPort;
use Qrk\Commerce\Shipping\Port\Persistence\AuditEventRepositoryPort;
use Qrk\Commerce\Shipping\Port\Persistence\SettingRepositoryPort;
use Qrk\Commerce\Shipping\Port\Persistence\TransactionManagerPort;

final class SettingsService
{
    /** @var array<string, ResolvedSetting> */
    private array $cache = [];

    public function __construct(
        private readonly SettingsCatalog $catalog,
        private readonly SettingValueCodec $codec,
        private readonly SettingRepositoryPort $repository,
        private readonly AuditEventRepositoryPort $auditEvents,
        private readonly TransactionManagerPort $transactions,
        private readonly ClockPort $clock,
    ) {
    }

    public function resolve(string $key, SettingScope $requestedScope): ResolvedSetting
    {
        $cacheKey = $key . '|' . $requestedScope->cacheKey();
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $definition = $this->catalog->get($key);
        foreach ($requestedScope->resolutionChain() as $scope) {
            $stored = $this->repository->find($key, $scope);
            if ($stored === null) {
                continue;
            }

            if (
                $stored->key() !== $key
                || $stored->type() !== $definition->type()
                || !$stored->scope()->equals($scope)
            ) {
                throw new \UnexpectedValueException(sprintf(
                    'Stored identity for setting "%s" does not match its requested catalog scope.',
                    $key,
                ));
            }

            return $this->cache[$cacheKey] = new ResolvedSetting(
                $definition,
                $this->codec->decode($definition, $stored->encodedValue(), $stored->isExplicitEmpty()),
                $scope,
                $stored->isExplicitEmpty(),
            );
        }

        return $this->cache[$cacheKey] = new ResolvedSetting(
            $definition,
            $this->codec->decode($definition, $definition->defaultEncodedValue(), false),
            null,
            false,
        );
    }

    public function set(string $key, SettingScope $scope, mixed $value, AuditActor $actor): void
    {
        $definition = $this->catalog->get($key);
        $encoded = $this->codec->encode($definition, $value);

        $this->transactions->run(function () use ($key, $scope, $actor, $definition, $encoded): void {
            $this->repository->save(new StoredSetting(
                $key,
                $definition->type(),
                $scope,
                $encoded->value(),
                $encoded->isExplicitEmpty(),
            ));

            $this->auditEvents->append(new AuditEvent(
                'setting.updated',
                'info',
                $scope,
                $actor,
                'setting',
                $key,
                self::correlationId(),
                [
                    'setting_key' => $key,
                    'scope' => $scope->cacheKey(),
                    'explicit_empty' => $encoded->isExplicitEmpty(),
                ],
                $this->clock->now(),
            ));
        });

        $this->cache = [];
    }

    public function removeOverride(string $key, SettingScope $scope, AuditActor $actor): void
    {
        $this->catalog->get($key);

        $this->transactions->run(function () use ($key, $scope, $actor): void {
            $this->repository->delete($key, $scope);

            $this->auditEvents->append(new AuditEvent(
                'setting.override_removed',
                'info',
                $scope,
                $actor,
                'setting',
                $key,
                self::correlationId(),
                [
                    'setting_key' => $key,
                    'scope' => $scope->cacheKey(),
                ],
                $this->clock->now(),
            ));
        });

        $this->cache = [];
    }

    private static function correlationId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
