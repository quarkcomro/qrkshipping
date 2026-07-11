<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Port\Persistence;

use Qrk\Commerce\Shipping\Domain\Security\EncryptedSecret;
use Qrk\Commerce\Shipping\Domain\Security\SecretLocator;

interface SecretRepositoryPort
{
    public function find(SecretLocator $locator): ?EncryptedSecret;

    public function save(SecretLocator $locator, EncryptedSecret $secret): void;

    public function delete(SecretLocator $locator): void;

    public function deleteAll(): int;
}
