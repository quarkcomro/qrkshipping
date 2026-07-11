<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Security;

use InvalidArgumentException;

final readonly class MasterKey
{
    public function __construct(
        private string $id,
        private string $bytes,
    ) {
        if ($id === '' || strlen($id) > 64) {
            throw new InvalidArgumentException('Master-key ID is invalid.');
        }

        if (strlen($bytes) < 32) {
            throw new InvalidArgumentException('Master-key material must contain at least 32 bytes.');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function bytes(): string
    {
        return $this->bytes;
    }
}
