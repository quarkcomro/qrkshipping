<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Port\Security;

use Qrk\Commerce\Shipping\Domain\Security\MasterKey;

interface MasterKeyProviderPort
{
    public function current(): MasterKey;

    public function byId(string $keyId): MasterKey;
}
