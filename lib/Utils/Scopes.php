<?php

namespace Frontender\Core\Utils;

use Frontender\Core\DB\Adapter;

class Scopes
{
    public static function get() {
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
        return self::parse($settings['scopes']);
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