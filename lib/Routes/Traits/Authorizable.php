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

namespace Frontender\Core\Routes\Traits;

use Slim\Exception\MethodNotAllowedException;
use Frontender\Core\DB\Adapter;
use Frontender\Core\Routes\Helpers\Tokenize;
use Slim\Http\Request;
use Slim\Http\Response;
use Frontender\Core\Routes\Exceptions\Unauthorized;

trait Authorizable
{
    public function isAuthorized(string $requiredPermission, Request $request, Response &$response): Response
    {
        // By default we will not allow the action.
        $allowed = false;

        // Get the site ID.
        $settings = Adapter::getInstance()->collection('settings')->find()->toArray();
        $settings = array_shift($settings);

        if(!$this->app->getContainer()->has('token')) {
	        throw new Unauthorized($request, $response);
        }

        $token = $this->app->getContainer()['token'];
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

        $allowed = in_array($requiredPermission, $permissions);

        if (!$allowed) {
            throw new Unauthorized($request, $response);
        }

        return $response;
    }
}
