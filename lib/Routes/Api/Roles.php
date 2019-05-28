<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\Routes\Helpers\CoreRoute;
use Slim\Http\Request;
use Slim\Http\Response;
use Frontender\Core\DB\Adapter;
use Frontender\Core\Routes\Middleware\TokenCheck;
use Frontender\Core\Routes\Middleware\ApiLocale;

class Roles extends CoreRoute
{
    protected $group = '/api/roles';

    public function registerReadRoutes()
    {
        parent::registerReadRoutes();

        $this->app->get('/users/{user_id}', function (Request $request, Response $response) {
            // The user is logged in.
            $userRoles = Adapter::getInstance()->collection('roles')->find([
                'users' => (int)$request->getAttribute('user_id')
            ])->toArray();
            $userRoles = Adapter::getInstance()->toJSON($userRoles);

            return $response->withJson(array_map(function ($role) {
                unset($role->users);

                return $role;
            }, $userRoles));
        });
    }

    public function getGroupMiddleware()
    {
        return [
            new TokenCheck(
                $this->app->getContainer()
            ),
            new ApiLocale($this->app->getContainer())
        ];
    }
}
