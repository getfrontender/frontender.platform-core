<?php
/*******************************************************
 * @copyright 2017-2019 Dipity B.V., The Netherlands
 * @package Frontender
 * @subpackage Frontender Platform Core
 *
 * Frontender is a web application development platform consisting of a
 * desktop application (Frontender Desktop) and a web application which
 * consists of a client component (Frontender Platform) and a core
 * component (Frontender Platform Core).
 *
 * Frontender Desktop, Frontender Platform and Frontender Platform Core
 * may not be copied and/or distributed without the express
 * permission of Dipity B.V.
 *******************************************************/

namespace Frontender\Core\Config;

class Config
{
    public function __construct()
    {
        $env = new \Dotenv\Dotenv(ROOT_PATH);

        if (getenv('ENV') === false) {
            $env->overload();
        }

        foreach (array_keys($_ENV) as $key) {
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
        return ['settings' => (array)$this];
    }
}
