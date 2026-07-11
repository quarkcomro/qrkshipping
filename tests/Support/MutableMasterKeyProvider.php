<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Support;

use Qrk\Commerce\Shipping\Domain\Security\MasterKey;
use Qrk\Commerce\Shipping\Port\Security\MasterKeyProviderPort;
use RuntimeException;

final class MutableMasterKeyProvider implements MasterKeyProviderPort
{
    /** @var array<string, MasterKey> */
    private array $keys = [];
    private MasterKey $current;

    public function __construct(MasterKey $current)
    {
        $this->setCurrent($current);
    }

    public function setCurrent(MasterKey $key): void
    {
        $this->keys[$key->id()] = $key;
        $this->current = $key;
    }

    public function current(): MasterKey
    {
        return $this->current;
    }

    public function byId(string $keyId): MasterKey
    {
        return $this->keys[$keyId] ?? throw new RuntimeException('Unknown test key.');
    }
}
