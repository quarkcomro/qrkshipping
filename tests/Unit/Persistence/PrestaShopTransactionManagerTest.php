<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Persistence;

use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Persistence\PrestaShopTransactionManager;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Persistence\TransactionOperationException;
use Qrk\Commerce\Shipping\Tests\Support\ScriptedDatabaseConnection;

final class PrestaShopTransactionManagerTest extends TestCase
{
    public function testCommitsSuccessfulOperation(): void
    {
        $connection = new ScriptedDatabaseConnection();
        $manager = new PrestaShopTransactionManager($connection);

        $result = $manager->run(static function () use ($connection): string {
            $connection->execute('UPDATE foundation');

            return 'committed';
        });

        self::assertSame('committed', $result);
        self::assertSame(
            ['START TRANSACTION', 'UPDATE foundation', 'COMMIT'],
            $connection->executed,
        );
    }

    public function testRollsBackFailedOperation(): void
    {
        $connection = new ScriptedDatabaseConnection(['UPDATE foundation']);
        $manager = new PrestaShopTransactionManager($connection);

        try {
            $manager->run(static function () use ($connection): void {
                $connection->execute('UPDATE foundation');
            });
            self::fail('Injected operation failure was not propagated.');
        } catch (TransactionOperationException $exception) {
            self::assertFalse($exception->rollbackFailed());
            self::assertSame(
                ['START TRANSACTION', 'UPDATE foundation', 'ROLLBACK'],
                $connection->executed,
            );
        }
    }

    public function testReportsRollbackFailureWithoutHidingOriginalFailure(): void
    {
        $connection = new ScriptedDatabaseConnection(['UPDATE foundation', 'ROLLBACK']);
        $manager = new PrestaShopTransactionManager($connection);

        try {
            $manager->run(static function () use ($connection): void {
                $connection->execute('UPDATE foundation');
            });
            self::fail('Injected operation failure was not propagated.');
        } catch (TransactionOperationException $exception) {
            self::assertTrue($exception->rollbackFailed());
            self::assertSame('Injected database failure.', $exception->getPrevious()?->getMessage());
        }
    }
}
