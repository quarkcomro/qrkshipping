<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Support;

use Closure;
use Qrk\Commerce\Shipping\Port\Persistence\TransactionManagerPort;

final class InMemoryTransactionManager implements TransactionManagerPort
{
    public int $runs = 0;

    /**
     * @template T
     *
     * @param Closure(): T $operation
     *
     * @return T
     */
    public function run(Closure $operation): mixed
    {
        ++$this->runs;

        return $operation();
    }
}
