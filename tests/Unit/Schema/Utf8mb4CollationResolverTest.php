<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\Utf8mb4CollationResolver;
use Qrk\Commerce\Shipping\Tests\Support\QueuedDatabaseConnection;

final class Utf8mb4CollationResolverTest extends TestCase
{
    public function testUtf8mb3DatabaseDefaultFallsBackToPortableUtf8mb4Collation(): void
    {
        $connection = new QueuedDatabaseConnection([
            ['collation' => 'utf8mb3_general_ci'],
            ['collation' => 'utf8mb4_unicode_ci'],
        ]);

        self::assertSame(
            'utf8mb4_unicode_ci',
            (new Utf8mb4CollationResolver($connection))->resolve(),
        );
    }

    public function testPortableDatabaseDefaultIsRetainedWhenAvailable(): void
    {
        $connection = new QueuedDatabaseConnection([
            ['collation' => 'utf8mb4_general_ci'],
        ]);

        self::assertSame(
            'utf8mb4_general_ci',
            (new Utf8mb4CollationResolver($connection))->resolve(),
        );
    }

    public function testMissingPortableUtf8mb4CollationsFailsClosed(): void
    {
        $connection = new QueuedDatabaseConnection([
            ['collation' => 'utf8mb3_general_ci'],
            null,
            null,
        ]);

        self::assertNull((new Utf8mb4CollationResolver($connection))->resolve());
    }
}
