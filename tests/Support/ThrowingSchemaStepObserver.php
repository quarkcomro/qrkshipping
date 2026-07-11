<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Support;

use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaStepObserver;
use RuntimeException;

final readonly class ThrowingSchemaStepObserver implements SchemaStepObserver
{
    public function __construct(
        private int $failureStep,
    ) {
    }

    public function afterTableCreated(string $tableName, int $step): void
    {
        if ($step === $this->failureStep) {
            throw new RuntimeException('Injected schema failure.');
        }
    }
}
