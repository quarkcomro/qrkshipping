<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Support;

use Qrk\Commerce\Shipping\Domain\Security\EncryptedSecret;
use Qrk\Commerce\Shipping\Domain\Security\SecretLocator;
use Qrk\Commerce\Shipping\Port\Persistence\SecretRepositoryPort;

final class InMemorySecretRepository implements SecretRepositoryPort
{
    /** @var array<string, EncryptedSecret> */
    private array $items = [];

    public function find(SecretLocator $locator): ?EncryptedSecret
    {
        return $this->items[$locator->subjectId()] ?? null;
    }

    public function save(SecretLocator $locator, EncryptedSecret $secret): void
    {
        $this->items[$locator->subjectId()] = $secret;
    }

    public function delete(SecretLocator $locator): void
    {
        unset($this->items[$locator->subjectId()]);
    }

    public function deleteAll(): int
    {
        $count = count($this->items);
        $this->items = [];

        return $count;
    }
}
