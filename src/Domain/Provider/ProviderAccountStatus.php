<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Provider;

enum ProviderAccountStatus: string
{
    case DRAFT = 'draft';
}
