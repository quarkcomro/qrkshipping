<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Upgrade;

use PHPUnit\Framework\TestCase;

final class Upgrade013Test extends TestCase
{
    public function testUpgradeIsAnExplicitNoOp(): void
    {
        if (!defined('_PS_VERSION_')) {
            define('_PS_VERSION_', '9.1.4');
        }
        require_once dirname(__DIR__, 3) . '/upgrade/upgrade-0.1.3.php';

        self::assertTrue(\upgrade_module_0_1_3(new \stdClass()));
    }
}
