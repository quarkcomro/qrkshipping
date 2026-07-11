<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Diagnostics;

use InvalidArgumentException;

final readonly class DiagnosticCheck
{
    /**
     * @param array<string, bool|int|string|null> $details
     */
    public function __construct(
        private string $code,
        private DiagnosticStatus $status,
        private array $details = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]{2,95}$/D', $code) !== 1) {
            throw new InvalidArgumentException('Diagnostic code is invalid.');
        }
    }

    public function code(): string
    {
        return $this->code;
    }

    public function status(): DiagnosticStatus
    {
        return $this->status;
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function details(): array
    {
        return $this->details;
    }
}
