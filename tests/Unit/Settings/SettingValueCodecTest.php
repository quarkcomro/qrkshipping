<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Settings;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Application\Settings\SettingValueCodec;
use Qrk\Commerce\Shipping\Domain\Settings\SettingDefinition;
use Qrk\Commerce\Shipping\Domain\Settings\SettingType;

final class SettingValueCodecTest extends TestCase
{
    private SettingValueCodec $codec;

    protected function setUp(): void
    {
        $this->codec = new SettingValueCodec();
    }

    public function testRejectsStoredStringOutsideAllowedCatalog(): void
    {
        $definition = new SettingDefinition(
            'test.catalog',
            SettingType::STRING,
            'standard',
            false,
            ['standard', 'detailed'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->codec->decode($definition, 'modified-manually', false);
    }

    public function testRejectsNonCanonicalStoredInteger(): void
    {
        $definition = new SettingDefinition('test.integer', SettingType::INTEGER, '0');

        $this->expectException(InvalidArgumentException::class);
        $this->codec->decode($definition, '01', false);
    }

    public function testRejectsNonCanonicalStoredDecimal(): void
    {
        $definition = new SettingDefinition('test.decimal', SettingType::DECIMAL, '1.25');

        $this->expectException(InvalidArgumentException::class);
        $this->codec->decode($definition, '1.250', false);
    }

    public function testExplicitEmptyRemainsDistinctAndMustBeAllowed(): void
    {
        $allowed = new SettingDefinition('test.empty', SettingType::STRING, 'fallback', true);
        self::assertSame('', $this->codec->decode($allowed, '', true));

        $forbidden = new SettingDefinition('test.required', SettingType::STRING, 'fallback');

        $this->expectException(InvalidArgumentException::class);
        $this->codec->decode($forbidden, '', false);
    }

    public function testStoredEmptyStringRequiresExplicitEmptyMarkerEvenWhenAllowed(): void
    {
        $definition = new SettingDefinition('test.empty', SettingType::STRING, 'fallback', true);

        $this->expectException(InvalidArgumentException::class);
        $this->codec->decode($definition, '', false);
    }
}
