<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema;

final class SchemaPlanner
{
    /**
     * @param list<string> $expectedTableNames
     * @param list<string> $existingExpectedTableNames
     */
    public function plan(array $expectedTableNames, array $existingExpectedTableNames): SchemaInstallPlan
    {
        $expected = array_values(array_unique($expectedTableNames));
        $existing = array_values(array_unique($existingExpectedTableNames));
        sort($expected);
        sort($existing);

        if ($existing === []) {
            return new SchemaInstallPlan(SchemaInstallAction::CREATE);
        }

        if ($existing === $expected) {
            return new SchemaInstallPlan(SchemaInstallAction::ADOPT_EXISTING);
        }

        $missing = array_values(array_diff($expected, $existing));
        $unexpected = array_values(array_diff($existing, $expected));

        throw new IncompatibleSchemaException(sprintf(
            'QRK Shipping found a partial or incompatible foundation schema. Missing: [%s]. Unexpected: [%s].',
            implode(', ', $missing),
            implode(', ', $unexpected),
        ));
    }
}
