<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Security;

use Qrk\Commerce\Shipping\Domain\Security\EncryptedSecret;
use Qrk\Commerce\Shipping\Domain\Security\MasterKey;
use Qrk\Commerce\Shipping\Port\Security\SecretCipherPort;

final class OpenSslAesGcmSecretCipher implements SecretCipherPort
{
    private const CIPHER = 'aes-256-gcm';
    private const CIPHER_VERSION = 1;
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH = 16;

    public function encrypt(string $plaintext, string $aad, MasterKey $masterKey): EncryptedSecret
    {
        if ($plaintext === '') {
            throw new SecretCipherException('An empty value cannot be encrypted as a stored secret.');
        }

        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->deriveKey($masterKey),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad,
            self::TAG_LENGTH,
        );

        if ($ciphertext === false || strlen($tag) !== self::TAG_LENGTH) {
            throw new SecretCipherException('Authenticated secret encryption failed.');
        }

        return new EncryptedSecret(
            self::CIPHER_VERSION,
            $masterKey->id(),
            base64_encode($nonce),
            base64_encode($ciphertext),
            base64_encode($tag),
        );
    }

    public function decrypt(EncryptedSecret $secret, string $aad, MasterKey $masterKey): string
    {
        if ($secret->cipherVersion() !== self::CIPHER_VERSION) {
            throw new SecretCipherException('Encrypted secret uses an unsupported cipher version.');
        }

        if ($secret->keyId() !== $masterKey->id()) {
            throw new SecretCipherException('Encrypted secret key identifier does not match the supplied key.');
        }

        $nonce = base64_decode($secret->nonceBase64(), true);
        $ciphertext = base64_decode($secret->ciphertextBase64(), true);
        $tag = base64_decode($secret->authTagBase64(), true);

        if (
            $nonce === false
            || $ciphertext === false
            || $tag === false
            || strlen($nonce) !== self::NONCE_LENGTH
            || strlen($tag) !== self::TAG_LENGTH
        ) {
            throw new SecretCipherException('Encrypted secret envelope is malformed.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->deriveKey($masterKey),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad,
        );

        if ($plaintext === false) {
            throw new SecretCipherException('Encrypted secret authentication failed.');
        }

        return $plaintext;
    }

    private function deriveKey(MasterKey $masterKey): string
    {
        return hash_hkdf(
            'sha256',
            $masterKey->bytes(),
            32,
            'qrkshipping:secret-envelope:v1',
            'qrkshipping',
        );
    }
}
