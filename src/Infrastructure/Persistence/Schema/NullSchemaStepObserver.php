<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema;

final class NullSchemaStepObserver implements SchemaStepObserver
{
    public function afterTableCreated(string $tableName, int $step): void
    {
    }
}
