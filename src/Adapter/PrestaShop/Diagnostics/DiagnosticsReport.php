<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Diagnostics;

final readonly class DiagnosticsReport
{
    /**
     * @param list<DiagnosticCheck> $checks
     */
    public function __construct(
        private array $checks,
    ) {
    }

    /**
     * @return list<DiagnosticCheck>
     */
    public function checks(): array
    {
        return $this->checks;
    }

    public function isHealthy(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->status() === DiagnosticStatus::FAIL) {
                return false;
            }
        }

        return true;
    }
}
