<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Install;

final readonly class RuntimeRequirementFailure
{
    /**
     * @param array<string, int|string> $parameters
     */
    public function __construct(
        private string $code,
        private array $parameters = [],
    ) {
    }

    public function code(): string
    {
        return $this->code;
    }

    /**
     * @return array<string, int|string>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
