<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Provider;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ProviderAccount
{
    private int $id;
    private ProviderCode $providerCode;
    private int $shopId;
    private string $label;
    private ProviderAccountStatus $status;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        int $id,
        ProviderCode $providerCode,
        int $shopId,
        string $label,
        ProviderAccountStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        if ($id < 0 || $shopId <= 0) {
            throw new InvalidArgumentException('Provider account identifiers are invalid.');
        }

        $label = trim($label);
        if ($label === '' || mb_strlen($label) > 160) {
            throw new InvalidArgumentException('Provider account label must contain between 1 and 160 characters.');
        }

        if ($updatedAt < $createdAt) {
            throw new InvalidArgumentException('Provider account update time cannot precede its creation time.');
        }

        $this->id = $id;
        $this->providerCode = $providerCode;
        $this->shopId = $shopId;
        $this->label = $label;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function providerCode(): ProviderCode
    {
        return $this->providerCode;
    }

    public function shopId(): int
    {
        return $this->shopId;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function status(): ProviderAccountStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
