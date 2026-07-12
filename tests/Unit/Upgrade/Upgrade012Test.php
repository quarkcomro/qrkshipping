<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Upgrade;

use PHPUnit\Framework\TestCase;

final class Upgrade012Test extends TestCase
{
    public function testUpgradeRegistersResetLifecycleHook(): void
    {
        if (!defined('_PS_VERSION_')) {
            define('_PS_VERSION_', '9.1.4');
        }
        require_once dirname(__DIR__, 3) . '/upgrade/upgrade-0.1.2.php';

        $module = new class {
            /** @var list<string> */
            public array $hooks = [];

            public function registerHook(string $hookName): bool
            {
                $this->hooks[] = $hookName;

                return true;
            }
        };

        self::assertTrue(\upgrade_module_0_1_2($module));
        self::assertSame(['actionBeforeResetModule'], $module->hooks);
    }
}
