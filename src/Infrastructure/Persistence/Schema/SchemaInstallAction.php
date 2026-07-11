<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema;

enum SchemaInstallAction: string
{
    case CREATE = 'create';
    case ADOPT_EXISTING = 'adopt_existing';
}
