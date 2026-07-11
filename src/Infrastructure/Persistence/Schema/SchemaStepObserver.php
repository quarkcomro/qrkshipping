<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema;

interface SchemaStepObserver
{
    public function afterTableCreated(string $tableName, int $step): void;
}
