<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Domain\Security\EncryptedSecret;
use Qrk\Commerce\Shipping\Domain\Security\MasterKey;
use Qrk\Commerce\Shipping\Infrastructure\Security\OpenSslAesGcmSecretCipher;
use Qrk\Commerce\Shipping\Infrastructure\Security\SecretCipherException;

final class SecretCipherTest extends TestCase
{
    private OpenSslAesGcmSecretCipher $cipher;
    private MasterKey $key;

    protected function setUp(): void
    {
        $this->cipher = new OpenSslAesGcmSecretCipher();
        $this->key = new MasterKey('key-a', str_repeat('a', 32));
    }

    public function testAuthenticatedRoundTripUsesUniqueNonces(): void
    {
        $first = $this->cipher->encrypt('token-value', 'aad', $this->key);
        $second = $this->cipher->encrypt('token-value', 'aad', $this->key);

        self::assertNotSame($first->nonceBase64(), $second->nonceBase64());
        self::assertNotSame($first->ciphertextBase64(), $second->ciphertextBase64());
        self::assertSame('token-value', $this->cipher->decrypt($first, 'aad', $this->key));
        self::assertSame('token-value', $this->cipher->decrypt($second, 'aad', $this->key));
    }

    public function testWrongAadIsRejected(): void
    {
        $secret = $this->cipher->encrypt('token-value', 'aad', $this->key);

        $this->expectException(SecretCipherException::class);
        $this->cipher->decrypt($secret, 'different-aad', $this->key);
    }

    public function testWrongKeyIsRejected(): void
    {
        $secret = $this->cipher->encrypt('token-value', 'aad', $this->key);

        $this->expectException(SecretCipherException::class);
        $this->cipher->decrypt($secret, 'aad', new MasterKey('key-b', str_repeat('b', 32)));
    }

    public function testCiphertextTamperingIsRejected(): void
    {
        $secret = $this->cipher->encrypt('token-value', 'aad', $this->key);
        $bytes = base64_decode($secret->ciphertextBase64(), true);
        self::assertIsString($bytes);
        $bytes[0] = chr(ord($bytes[0]) ^ 1);
        $tampered = new EncryptedSecret(
            $secret->cipherVersion(),
            $secret->keyId(),
            $secret->nonceBase64(),
            base64_encode($bytes),
            $secret->authTagBase64(),
        );

        $this->expectException(SecretCipherException::class);
        $this->cipher->decrypt($tampered, 'aad', $this->key);
    }

    public function testTagTamperingIsRejected(): void
    {
        $secret = $this->cipher->encrypt('token-value', 'aad', $this->key);
        $bytes = base64_decode($secret->authTagBase64(), true);
        self::assertIsString($bytes);
        $bytes[0] = chr(ord($bytes[0]) ^ 1);
        $tampered = new EncryptedSecret(
            $secret->cipherVersion(),
            $secret->keyId(),
            $secret->nonceBase64(),
            $secret->ciphertextBase64(),
            base64_encode($bytes),
        );

        $this->expectException(SecretCipherException::class);
        $this->cipher->decrypt($tampered, 'aad', $this->key);
    }
}
