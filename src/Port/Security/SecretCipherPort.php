<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Port\Security;

use Qrk\Commerce\Shipping\Domain\Security\EncryptedSecret;
use Qrk\Commerce\Shipping\Domain\Security\MasterKey;

interface SecretCipherPort
{
    public function encrypt(string $plaintext, string $aad, MasterKey $masterKey): EncryptedSecret;

    public function decrypt(EncryptedSecret $secret, string $aad, MasterKey $masterKey): string;
}
