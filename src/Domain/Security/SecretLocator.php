<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Security;

use InvalidArgumentException;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;

final readonly class SecretLocator
{
    public function __construct(
        private int $providerAccountId,
        private SettingScope $scope,
        private string $secretKey,
    ) {
        if ($providerAccountId < 0) {
            throw new InvalidArgumentException('Provider account ID cannot be negative.');
        }

        if (preg_match('/^[a-z][a-z0-9_.-]{2,127}$/D', $secretKey) !== 1) {
            throw new InvalidArgumentException('Secret key is invalid.');
        }
    }

    public function providerAccountId(): int
    {
        return $this->providerAccountId;
    }

    public function scope(): SettingScope
    {
        return $this->scope;
    }

    public function secretKey(): string
    {
        return $this->secretKey;
    }

    public function aad(int $cipherVersion): string
    {
        return implode('|', [
            'qrkshipping',
            'secret',
            'v' . $cipherVersion,
            (string) $this->providerAccountId,
            $this->scope->type()->value,
            (string) $this->scope->shopGroupId(),
            (string) $this->scope->shopId(),
            $this->secretKey,
        ]);
    }

    public function subjectId(): string
    {
        return hash('sha256', implode('|', [
            (string) $this->providerAccountId,
            $this->scope->cacheKey(),
            $this->secretKey,
        ]));
    }
}
