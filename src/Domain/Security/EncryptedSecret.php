<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Security;

use InvalidArgumentException;

final readonly class EncryptedSecret
{
    public function __construct(
        private int $cipherVersion,
        private string $keyId,
        private string $nonceBase64,
        private string $ciphertextBase64,
        private string $authTagBase64,
    ) {
        if ($cipherVersion <= 0) {
            throw new InvalidArgumentException('Cipher version must be positive.');
        }

        if ($keyId === '' || strlen($keyId) > 64) {
            throw new InvalidArgumentException('Secret key ID is invalid.');
        }

        foreach ([$nonceBase64, $ciphertextBase64, $authTagBase64] as $encodedPart) {
            if ($encodedPart === '' || base64_decode($encodedPart, true) === false) {
                throw new InvalidArgumentException('Encrypted-secret envelope contains invalid base64.');
            }
        }
    }

    public function cipherVersion(): int
    {
        return $this->cipherVersion;
    }

    public function keyId(): string
    {
        return $this->keyId;
    }

    public function nonceBase64(): string
    {
        return $this->nonceBase64;
    }

    public function ciphertextBase64(): string
    {
        return $this->ciphertextBase64;
    }

    public function authTagBase64(): string
    {
        return $this->authTagBase64;
    }
}
