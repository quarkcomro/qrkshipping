<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The 0.1.3 upgrade changes only Back Office access policy evaluation.
 */
function upgrade_module_0_1_3(object $module): bool
{
    unset($module);

    return true;
}
