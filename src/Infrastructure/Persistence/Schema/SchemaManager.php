<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema;

use Qrk\Commerce\Shipping\Port\Clock\ClockPort;
use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;
use Throwable;

final class SchemaManager
{
    public function __construct(
        private readonly DatabaseConnectionPort $connection,
        private readonly SchemaCatalog $catalog,
        private readonly SchemaInspector $inspector,
        private readonly SchemaPlanner $planner,
        private readonly MigrationRecorder $migrationRecorder,
        private readonly ClockPort $clock,
        private readonly string $databasePrefix,
        private readonly SchemaStepObserver $stepObserver = new NullSchemaStepObserver(),
    ) {
    }

    public function install(): SchemaInstallAction
    {
        $expectedNames = $this->expectedTableNames();
        $existingNames = $this->inspector->existingExpectedTableNames($this->catalog);
        $plan = $this->planner->plan($expectedNames, $existingNames);

        if ($plan->action() === SchemaInstallAction::ADOPT_EXISTING) {
            $this->inspector->assertCompatible($this->catalog);
            $this->migrationRecorder->ensureCurrent($this->catalog, $this->clock->now());

            return SchemaInstallAction::ADOPT_EXISTING;
        }

        $collation = $this->inspector->currentUtf8mb4Collation();
        $created = [];

        try {
            foreach ($this->catalog->tables() as $step => $table) {
                $fullName = $table->fullName($this->databasePrefix);
                $this->connection->execute($table->createSql($this->databasePrefix, $collation));
                $created[] = $fullName;
                $this->stepObserver->afterTableCreated($fullName, $step + 1);
            }

            $this->inspector->assertCompatible($this->catalog);
            $this->migrationRecorder->ensureCurrent($this->catalog, $this->clock->now());

            return SchemaInstallAction::CREATE;
        } catch (Throwable $exception) {
            $rollbackFailures = $this->rollbackTables($created);

            throw new SchemaInstallationException(
                'QRK Shipping foundation schema installation failed and rollback was attempted.',
                $exception,
                $rollbackFailures,
            );
        }
    }

    /**
     * Roll back a foundation that was known to be absent before the current install attempt.
     * Callers must invoke this only after install() returned CREATE.
     *
     * @return list<string> table names that could not be removed
     */
    public function rollbackFreshInstallation(): array
    {
        return $this->rollbackTables($this->expectedTableNames());
    }

    public function assertHealthy(): void
    {
        $this->inspector->assertCompatible($this->catalog);
        $this->migrationRecorder->assertCurrent($this->catalog);
    }

    public function fingerprint(): string
    {
        return $this->catalog->fingerprint();
    }

    /**
     * @return list<string>
     */
    private function expectedTableNames(): array
    {
        $expectedNames = array_map(
            fn (TableDefinition $table): string => $table->fullName($this->databasePrefix),
            $this->catalog->tables(),
        );
        sort($expectedNames);

        return $expectedNames;
    }

    /**
     * @param list<string> $tables
     *
     * @return list<string>
     */
    private function rollbackTables(array $tables): array
    {
        $failures = [];
        foreach (array_reverse($tables) as $tableName) {
            try {
                $this->connection->execute(sprintf('DROP TABLE IF EXISTS `%s`', $tableName));
            } catch (Throwable) {
                $failures[] = $tableName;
            }
        }

        return $failures;
    }
}
