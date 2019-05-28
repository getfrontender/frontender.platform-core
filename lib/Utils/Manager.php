<?php

namespace Frontender\Core\Utils;

use GuzzleHttp\Client;
use Slim\Container;
use Frontender\Core\Config\Config;

class Manager extends Client
{
    private static $instance = null;

    private $apiVersion = '2';

    public function __construct()
    {
        $config = new Config();

        parent::__construct([
            'base_uri' => sprintf('%s/api/v%s/', $config->fem_host, $this->apiVersion)
        ]);
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new Manager();
        }

        return self::$instance;
    }
}
