<?php

namespace Frontender\Core\Script;

use Frontender\Core\Config\Config;

class Base
{
    public static function loadEnv()
    {
        define('ROOT_PATH', getcwd());

        require_once getcwd() . '/vendor/autoload.php';
        
        new Config();
    }
}