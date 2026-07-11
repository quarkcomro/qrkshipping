<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Port\Persistence;

use Closure;

interface TransactionManagerPort
{
    /**
     * @template T
     *
     * @param Closure(): T $operation
     *
     * @return T
     */
    public function run(Closure $operation): mixed;
}
