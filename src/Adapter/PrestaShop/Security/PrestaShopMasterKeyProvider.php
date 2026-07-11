<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Security;

use Qrk\Commerce\Shipping\Domain\Security\MasterKey;
use Qrk\Commerce\Shipping\Port\Security\MasterKeyProviderPort;
use RuntimeException;

final class PrestaShopMasterKeyProvider implements MasterKeyProviderPort
{
    public function current(): MasterKey
    {
        $material = $this->platformMaterial();
        $id = 'ps-cookie-' . substr(hash('sha256', $material), 0, 24);

        return new MasterKey(
            $id,
            hash('sha512', "qrkshipping-master\0" . $material, true),
        );
    }

    public function byId(string $keyId): MasterKey
    {
        $current = $this->current();
        if (!hash_equals($current->id(), $keyId)) {
            throw new RuntimeException('Requested historical master key is not available.');
        }

        return $current;
    }

    private function platformMaterial(): string
    {
        if (!defined('_COOKIE_KEY_')) {
            throw new RuntimeException('PrestaShop cookie-key material is unavailable.');
        }

        $cookieKey = (string) constant('_COOKIE_KEY_');
        if (strlen($cookieKey) < 32) {
            throw new RuntimeException('PrestaShop cookie-key material is too short.');
        }

        $cookieIv = defined('_COOKIE_IV_') ? (string) constant('_COOKIE_IV_') : '';

        return $cookieKey . "\0" . $cookieIv;
    }
}
