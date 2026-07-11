<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Persistence;

use Closure;
use LogicException;
use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;
use Qrk\Commerce\Shipping\Port\Persistence\TransactionManagerPort;
use Throwable;

final class PrestaShopTransactionManager implements TransactionManagerPort
{
    private bool $active = false;

    public function __construct(
        private readonly DatabaseConnectionPort $connection,
    ) {
    }

    /**
     * @template T
     *
     * @param Closure(): T $operation
     *
     * @return T
     */
    public function run(Closure $operation): mixed
    {
        if ($this->active) {
            throw new LogicException('Nested QRK Shipping transactions are not supported.');
        }

        $this->connection->execute('START TRANSACTION');
        $this->active = true;

        try {
            $result = $operation();
            $this->connection->execute('COMMIT');
            $this->active = false;

            return $result;
        } catch (Throwable $exception) {
            $rollbackFailed = false;

            try {
                $this->connection->execute('ROLLBACK');
            } catch (Throwable) {
                $rollbackFailed = true;
            }

            $this->active = false;

            throw new TransactionOperationException(
                'QRK Shipping transactional operation failed and rollback was attempted.',
                $exception,
                $rollbackFailed,
            );
        }
    }
}
