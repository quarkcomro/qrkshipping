<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Application\Provider;

use InvalidArgumentException;
use Qrk\Commerce\Shipping\Domain\Audit\AuditActor;
use Qrk\Commerce\Shipping\Domain\Audit\AuditEvent;
use Qrk\Commerce\Shipping\Domain\Provider\ProviderAccount;
use Qrk\Commerce\Shipping\Domain\Provider\ProviderCode;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Port\Clock\ClockPort;
use Qrk\Commerce\Shipping\Port\Persistence\AuditEventRepositoryPort;
use Qrk\Commerce\Shipping\Port\Persistence\ProviderAccountRepositoryPort;
use Qrk\Commerce\Shipping\Port\Persistence\TransactionManagerPort;

final class ProviderAccountService
{
    public function __construct(
        private readonly ProviderAccountRepositoryPort $repository,
        private readonly AuditEventRepositoryPort $auditEvents,
        private readonly TransactionManagerPort $transactions,
        private readonly ClockPort $clock,
    ) {
    }

    public function find(ProviderCode $providerCode, int $shopId): ?ProviderAccount
    {
        if ($shopId <= 0) {
            throw new InvalidArgumentException('Provider accounts require a positive shop ID.');
        }

        return $this->repository->find($providerCode, $shopId);
    }

    public function saveDraftLabel(
        ProviderCode $providerCode,
        int $shopId,
        int $shopGroupId,
        string $label,
        AuditActor $actor,
    ): ProviderAccount {
        $label = trim($label);
        if ($label === '' || mb_strlen($label) > 160) {
            throw new InvalidArgumentException('Provider account label must contain between 1 and 160 characters.');
        }

        $scope = SettingScope::shop($shopId, $shopGroupId);

        return $this->transactions->run(function () use (
            $providerCode,
            $shopId,
            $label,
            $scope,
            $actor,
        ): ProviderAccount {
            $account = $this->repository->saveDraft($providerCode, $shopId, $label);
            $this->auditEvents->append(new AuditEvent(
                'provider_account.draft_saved',
                'info',
                $scope,
                $actor,
                'provider_account',
                (string) $account->id(),
                bin2hex(random_bytes(16)),
                [
                    'provider_code' => $providerCode->value(),
                    'shop_id' => $shopId,
                    'status' => $account->status()->value,
                ],
                $this->clock->now(),
            ));

            return $account;
        });
    }
}
