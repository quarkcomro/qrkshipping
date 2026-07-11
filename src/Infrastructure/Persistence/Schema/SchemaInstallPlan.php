<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema;

final readonly class SchemaInstallPlan
{
    public function __construct(
        private SchemaInstallAction $action,
    ) {
    }

    public function action(): SchemaInstallAction
    {
        return $this->action;
    }
}
