<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Application\Settings;

use InvalidArgumentException;
use Qrk\Commerce\Shipping\Domain\Settings\SettingDefinition;
use Qrk\Commerce\Shipping\Domain\Settings\SettingType;

final class SettingsCatalog
{
    public const DIAGNOSTICS_DETAIL_LEVEL = 'diagnostics.detail_level';
    public const LIFECYCLE_PURGE_ON_UNINSTALL = 'lifecycle.purge_on_uninstall';
    public const LIFECYCLE_RESET_TO_DEFAULTS = 'lifecycle.reset_to_defaults';

    /** @var array<string, SettingDefinition> */
    private array $definitions;

    /**
     * @param iterable<SettingDefinition>|null $definitions
     */
    public function __construct(?iterable $definitions = null)
    {
        $definitions ??= [
            new SettingDefinition(
                self::DIAGNOSTICS_DETAIL_LEVEL,
                SettingType::STRING,
                'standard',
                false,
                ['standard', 'detailed'],
            ),
            new SettingDefinition(
                self::LIFECYCLE_PURGE_ON_UNINSTALL,
                SettingType::BOOLEAN,
                '0',
            ),
            new SettingDefinition(
                self::LIFECYCLE_RESET_TO_DEFAULTS,
                SettingType::BOOLEAN,
                '0',
            ),
        ];

        $this->definitions = [];
        foreach ($definitions as $definition) {
            if (isset($this->definitions[$definition->key()])) {
                throw new InvalidArgumentException(sprintf(
                    'Duplicate setting definition "%s".',
                    $definition->key(),
                ));
            }

            $this->definitions[$definition->key()] = $definition;
        }
    }

    public function get(string $key): SettingDefinition
    {
        return $this->definitions[$key]
            ?? throw new InvalidArgumentException(sprintf('Unknown setting "%s".', $key));
    }

    /**
     * @return list<SettingDefinition>
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }
}
