<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Lifecycle;

use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Lifecycle\LifecycleOperationDetector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class LifecycleOperationDetectorTest extends TestCase
{
    public function testDetectsBackOfficeResetFromRouteAttributes(): void
    {
        $requestStack = new RequestStack();
        $request = Request::create('/admin/modules/manage/action/reset/qrkshipping', 'POST');
        $request->attributes->set('action', 'reset');
        $request->attributes->set('module_name', 'qrkshipping');
        $requestStack->push($request);

        $detector = new LifecycleOperationDetector($requestStack, []);

        self::assertTrue($detector->isResetFor('qrkshipping'));
        self::assertFalse($detector->isResetFor('anothermodule'));
    }

    public function testDetectsOfficialPrestaShopConsoleReset(): void
    {
        $detector = new LifecycleOperationDetector(null, [
            'bin/console',
            'prestashop:module',
            'reset',
            'qrkshipping',
            '--env=prod',
        ]);

        self::assertTrue($detector->isResetFor('qrkshipping'));
    }

    public function testDoesNotClassifyUninstallAsReset(): void
    {
        $detector = new LifecycleOperationDetector(null, [
            'bin/console',
            'prestashop:module',
            'uninstall',
            'qrkshipping',
        ]);

        self::assertFalse($detector->isResetFor('qrkshipping'));
    }
}
