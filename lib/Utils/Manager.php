<?php

namespace Frontender\Core\Utils;

use GuzzleHttp\Client;
use Slim\Container;
use Frontender\Core\Config\Config;
use Frontender\Core\Routes\Helpers\Tokenize;
use Frontender\Core\DB\Adapter;

class Manager extends Client
{
    private static $instance = null;

    private $apiVersion = '2';
    private $token;

    public function __construct()
    {
        $config = new Config();

        parent::__construct([
            'base_uri' => sprintf('%s/api/v%s/', $config->fem_host, $this->apiVersion)
        ]);
    }

    public function request($method, $uri = '', array $options = [])
    {
        if ($this->token) {
            $token = clone $this->token;
            $roles = Adapter::getInstance()->collection('roles')->find([
                'users' => (int)$token->getClaim('sub')
            ])->toArray();
            $roles = Adapter::getInstance()->toJSON($roles);
            $permissions = array_map(function ($role) {
                return $role->permissions;
            }, $roles);
            $permissions = array_reduce($permissions, function ($carry, $values) {
                return array_merge($carry, $values);
            }, []);
            $permissions = array_unique($permissions);

            $token->set('permissions', $permissions);

            $settings = Adapter::getInstance()->collection('settings')->find()->toArray();
            $setting = array_shift($settings);

            $options = array_merge($options, [
                'headers' => [
                    'X-Token' => Tokenize::getInstance()->build($token)->__toString(),
                    'X-Site-ID' => $setting->site_id
                ]
            ]);
        }

        return parent::request($method, $uri, $options);
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new Manager();
        }

        return self::$instance;
    }
}
