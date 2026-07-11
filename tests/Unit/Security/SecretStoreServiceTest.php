<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Security;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Application\Security\SecretStoreService;
use Qrk\Commerce\Shipping\Domain\Audit\AuditActor;
use Qrk\Commerce\Shipping\Domain\Security\MasterKey;
use Qrk\Commerce\Shipping\Domain\Security\SecretLocator;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Infrastructure\Security\OpenSslAesGcmSecretCipher;
use Qrk\Commerce\Shipping\Tests\Support\FrozenClock;
use Qrk\Commerce\Shipping\Tests\Support\InMemoryAuditEventRepository;
use Qrk\Commerce\Shipping\Tests\Support\InMemorySecretRepository;
use Qrk\Commerce\Shipping\Tests\Support\InMemoryTransactionManager;
use Qrk\Commerce\Shipping\Tests\Support\MutableMasterKeyProvider;

final class SecretStoreServiceTest extends TestCase
{
    public function testEmptyEditPreservesExistingSecretAndExplicitDeleteRemovesIt(): void
    {
        [$service, , $audit] = $this->service();
        $locator = $this->locator();
        $actor = AuditActor::employee(3);

        self::assertTrue($service->write($locator, 'plain-secret', $actor));
        self::assertSame('plain-secret', $service->read($locator));
        self::assertFalse($service->write($locator, '', $actor));
        self::assertSame('plain-secret', $service->read($locator));

        $service->remove($locator, $actor);
        self::assertNull($service->read($locator));
        self::assertCount(2, $audit->events);
    }

    public function testRotationMovesEnvelopeToCurrentKeyAndKeepsPlaintextOutOfAudit(): void
    {
        [$service, $repository, $audit, $keys] = $this->service();
        $locator = $this->locator();
        $actor = AuditActor::employee(8);
        $service->write($locator, 'rotation-secret', $actor);
        $oldEnvelope = $repository->find($locator);
        self::assertNotNull($oldEnvelope);
        self::assertSame('key-1', $oldEnvelope->keyId());

        $keys->setCurrent(new MasterKey('key-2', str_repeat('2', 32)));
        self::assertTrue($service->rotate($locator, $actor));
        self::assertSame('rotation-secret', $service->read($locator));
        $newEnvelope = $repository->find($locator);
        self::assertNotNull($newEnvelope);
        self::assertSame('key-2', $newEnvelope->keyId());
        self::assertNotSame($oldEnvelope->ciphertextBase64(), $newEnvelope->ciphertextBase64());
        self::assertCount(2, $audit->events);
        self::assertSame('secret.rotated', $audit->events[1]->eventCode());

        foreach ($audit->events as $event) {
            self::assertStringNotContainsString('rotation-secret', $event->metadataJson());
        }
    }

    /**
     * @return array{
     *   SecretStoreService,
     *   InMemorySecretRepository,
     *   InMemoryAuditEventRepository,
     *   MutableMasterKeyProvider
     * }
     */
    private function service(): array
    {
        $repository = new InMemorySecretRepository();
        $audit = new InMemoryAuditEventRepository();
        $keys = new MutableMasterKeyProvider(new MasterKey('key-1', str_repeat('1', 32)));
        $service = new SecretStoreService(
            $repository,
            $keys,
            new OpenSslAesGcmSecretCipher(),
            $audit,
            new InMemoryTransactionManager(),
            new FrozenClock(new DateTimeImmutable('2026-07-11T12:00:00+00:00', new DateTimeZone('UTC'))),
        );

        return [$service, $repository, $audit, $keys];
    }

    private function locator(): SecretLocator
    {
        return new SecretLocator(12, SettingScope::shop(7, 4), 'api_token');
    }
}
