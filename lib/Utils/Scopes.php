<?php

namespace Frontender\Core\Utils;

use Frontender\Core\DB\Adapter;

class Scopes
{
    protected static $scopes;

    public static function get() {
        if(!self::$scopes) {
            $settings = Adapter::getInstance()
                ->toJSON(
                    Adapter::getInstance()
                    ->collection('settings')
                    ->find()
                    ->toArray()
                , true);

            if(!$settings) {
                return false;
            }

            $settings = array_shift($settings);
            self::$scopes = self::parse($settings['scopes']);
        }

        return self::$scopes;
    }

    public static function parse($scopes) {
        return array_reduce($scopes, function($carry, $scope) {
            $scopes = array_map(function($subScope) use ($scope) {
                $subScope['path'] = $scope['path'];

                return $subScope;
            }, $scope['scopes']);

            return array_merge($carry, $scopes);
        }, []);
    }
}