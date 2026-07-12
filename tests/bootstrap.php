<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!class_exists('CarrierModule')) {
    require_once dirname(__DIR__) . '/stubs/prestashop.php';
}
