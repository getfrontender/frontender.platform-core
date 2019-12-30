<?php

namespace Frontender\Core\Utils;

use Frontender\Core\DB\Adapter;

class Scopes
{
    protected static $scopes;
    protected static $groups;

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
                return [];
            }

            $settings = array_shift($settings);

            if(!isset($settings['scopes'])) {
            	return [];
            }

            self::$scopes = self::parse($settings['scopes']);
        }

        return self::$scopes;
    }

    public static function getGroups() {
        if(!self::$groups) {
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
            self::$groups = $settings['scopes'];
        }

        return self::$groups;
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

    public static function filterActiveScopes($scopes)
    {
        // Clone the array.
        $scopes = json_decode(json_encode($scopes));
        $scopes = array_map(function($scope) {
            foreach($scope->scopes as $index => $subScope) {
                if(!$subScope->isActive) {
                    unset($scope->scopes[$index]);
                }
            }

            return $scope;
        }, $scopes);
        $scopes = array_filter($scopes, function($scope) {
            return count($scope->scopes);
        });

        return $scopes;
    }
}