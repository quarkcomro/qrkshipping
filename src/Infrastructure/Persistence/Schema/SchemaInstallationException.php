<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema;

use RuntimeException;
use Throwable;

final class SchemaInstallationException extends RuntimeException
{
    /**
     * @param list<string> $rollbackFailures
     */
    public function __construct(
        string $message,
        Throwable $previous,
        private readonly array $rollbackFailures = [],
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @return list<string>
     */
    public function rollbackFailures(): array
    {
        return $this->rollbackFailures;
    }
}
