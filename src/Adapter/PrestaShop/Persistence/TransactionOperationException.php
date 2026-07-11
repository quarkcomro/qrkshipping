<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Persistence;

use RuntimeException;
use Throwable;

final class TransactionOperationException extends RuntimeException
{
    public function __construct(
        string $message,
        Throwable $previous,
        private readonly bool $rollbackFailed,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function rollbackFailed(): bool
    {
        return $this->rollbackFailed;
    }
}
