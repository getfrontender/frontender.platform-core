<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Config;

class Config
{
    public function __construct()
    {
        $env = new \Dotenv\Dotenv(ROOT_PATH);

        if (getenv('ENV') === false) {
            $env->overload();
        }

        $keys = array_diff(array_keys($_ENV), array_keys(getenv()));

        foreach($keys as $key) {
        	$this->{strtolower($key)} = $_ENV[$key];
        }

        /**
         * Load application configuration
         */
        $files = [
            '/environment.php',
            '/environments/' . $this->env . '.php'
        ];

        foreach ($files as $file) {
            if (file_exists(ROOT_PATH . '/config/' . $file)) {
                $config = require ROOT_PATH . '/config/' . $file;

                foreach ($config as $key => $value) {
                    $this->$key = $value;
                }
            }
        }

        /**
         * Override application configuration if defined in environment
         */
        if (getenv('CACHE') !== false) {
            $this->caching = getenv('CACHE');
        }

        if (isset($this->debug)) {
            $this->displayErrorDetails = $this->debug;
        }
    }

    public function toArray()
    {
        return ['settings' => (array) $this];
    }
}