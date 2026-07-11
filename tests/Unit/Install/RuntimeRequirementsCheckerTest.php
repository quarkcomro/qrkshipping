<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Install;

use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Install\RuntimeRequirementFailure;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Install\RuntimeRequirementsChecker;
use Qrk\Commerce\Shipping\Tests\Support\QueuedDatabaseConnection;

final class RuntimeRequirementsCheckerTest extends TestCase
{
    public function testUtf8mb3DatabaseDefaultIsAcceptedWhenPortableUtf8mb4IsAvailable(): void
    {
        if (!defined('_PS_VERSION_')) {
            define('_PS_VERSION_', '9.1.4');
        }

        $connection = new QueuedDatabaseConnection([
            [
                'version' => '10.11.9-MariaDB',
                'version_comment' => 'MariaDB Server',
                'engine' => 'InnoDB',
            ],
            ['collation' => 'utf8mb3_general_ci'],
            ['collation' => 'utf8mb4_unicode_ci'],
        ]);

        $failureCodes = array_map(
            static fn (RuntimeRequirementFailure $failure): string => $failure->code(),
            (new RuntimeRequirementsChecker($connection))->check(),
        );

        self::assertSame([], $failureCodes);
    }
}
