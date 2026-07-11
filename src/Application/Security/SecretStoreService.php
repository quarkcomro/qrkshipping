<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Application\Security;

use Qrk\Commerce\Shipping\Domain\Audit\AuditActor;
use Qrk\Commerce\Shipping\Domain\Audit\AuditEvent;
use Qrk\Commerce\Shipping\Domain\Security\EncryptedSecret;
use Qrk\Commerce\Shipping\Domain\Security\SecretLocator;
use Qrk\Commerce\Shipping\Port\Clock\ClockPort;
use Qrk\Commerce\Shipping\Port\Persistence\AuditEventRepositoryPort;
use Qrk\Commerce\Shipping\Port\Persistence\SecretRepositoryPort;
use Qrk\Commerce\Shipping\Port\Persistence\TransactionManagerPort;
use Qrk\Commerce\Shipping\Port\Security\MasterKeyProviderPort;
use Qrk\Commerce\Shipping\Port\Security\SecretCipherPort;

final class SecretStoreService
{
    public function __construct(
        private readonly SecretRepositoryPort $repository,
        private readonly MasterKeyProviderPort $keyProvider,
        private readonly SecretCipherPort $cipher,
        private readonly AuditEventRepositoryPort $auditEvents,
        private readonly TransactionManagerPort $transactions,
        private readonly ClockPort $clock,
    ) {
    }

    /**
     * Empty input deliberately preserves an existing secret.
     *
     * @return bool true when a new encrypted value was persisted
     */
    public function write(SecretLocator $locator, string $plaintext, AuditActor $actor): bool
    {
        if ($plaintext === '') {
            return false;
        }

        $encrypted = $this->encrypt($locator, $plaintext);
        $this->persistWithAudit($locator, $encrypted, $actor, 'secret.updated');

        return true;
    }

    public function read(SecretLocator $locator): ?string
    {
        $encrypted = $this->repository->find($locator);
        if ($encrypted === null) {
            return null;
        }

        $masterKey = $this->keyProvider->byId($encrypted->keyId());

        return $this->cipher->decrypt(
            $encrypted,
            $locator->aad($encrypted->cipherVersion()),
            $masterKey,
        );
    }

    public function remove(SecretLocator $locator, AuditActor $actor): void
    {
        $this->transactions->run(function () use ($locator, $actor): void {
            $this->repository->delete($locator);
            $this->auditEvents->append($this->auditEvent($locator, $actor, 'secret.deleted'));
        });
    }

    public function rotate(SecretLocator $locator, AuditActor $actor): bool
    {
        $plaintext = $this->read($locator);
        if ($plaintext === null) {
            return false;
        }

        $encrypted = $this->encrypt($locator, $plaintext);
        $this->persistWithAudit($locator, $encrypted, $actor, 'secret.rotated');

        return true;
    }

    private function encrypt(SecretLocator $locator, string $plaintext): EncryptedSecret
    {
        $currentKey = $this->keyProvider->current();

        return $this->cipher->encrypt(
            $plaintext,
            $locator->aad(1),
            $currentKey,
        );
    }

    private function persistWithAudit(
        SecretLocator $locator,
        EncryptedSecret $encrypted,
        AuditActor $actor,
        string $eventCode,
    ): void {
        $this->transactions->run(function () use ($locator, $encrypted, $actor, $eventCode): void {
            $this->repository->save($locator, $encrypted);
            $this->auditEvents->append($this->auditEvent(
                $locator,
                $actor,
                $eventCode,
                [
                    'cipher_version' => $encrypted->cipherVersion(),
                    'key_id' => $encrypted->keyId(),
                ],
            ));
        });
    }

    /**
     * @param array<string, bool|int|string|null> $metadata
     */
    private function auditEvent(
        SecretLocator $locator,
        AuditActor $actor,
        string $eventCode,
        array $metadata = [],
    ): AuditEvent {
        return new AuditEvent(
            $eventCode,
            'info',
            $locator->scope(),
            $actor,
            'secret',
            $locator->subjectId(),
            self::correlationId(),
            [
                'provider_account_id' => $locator->providerAccountId(),
                'secret_key_hash' => hash('sha256', $locator->secretKey()),
                ...$metadata,
            ],
            $this->clock->now(),
        );
    }

    private static function correlationId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
