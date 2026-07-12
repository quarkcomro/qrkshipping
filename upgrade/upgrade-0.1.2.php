<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @param object $module PrestaShop module instance
 */
function upgrade_module_0_1_2(object $module): bool
{
    if (!method_exists($module, 'registerHook')) {
        return false;
    }

    return (bool) $module->registerHook('actionBeforeResetModule');
}
