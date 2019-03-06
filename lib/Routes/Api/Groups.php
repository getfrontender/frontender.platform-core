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

namespace Frontender\Core\Routes\Api;

use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;
use Slim\Http\Request;
use Slim\Http\Response;
use Symfony\Component\Finder\Finder;
use Frontender\Core\Routes\Middleware\TokenCheck;
use Frontender\Core\DB\Adapter;
use MongoDB\BSON\ObjectId;

class Groups extends CoreRoute
{
    protected $group = '/api/groups';

    use Authorizable;

    public function registerReadRoutes()
    {
        parent::registerReadRoutes();

        $self = $this;

        $this->app->get('', function (Request $request, Response $response) use ($self) {
            $self->isAuthorized('space-administrator', $request, $response);

            $groupsCollection = Adapter::getInstance()->collection('groups');

            function appendChildren(&$group, $groupsCollection)
            {
                $group->_id = $group->_id->__toString();
                $children = $groupsCollection->find([
                    'parent_group_id' => new ObjectId($group->_id)
                ])->toArray();

                if ($children) {
                    $group->children = array_map(function ($group) use ($groupsCollection) {
                        appendChildren($group, $groupsCollection);

                        return $group;
                    }, $children);
                }
            }

            $rootGroup = $groupsCollection->find([
                'parent_group_id' => ['$exists' => false]
            ])->toArray();
            $rootGroup = array_shift($rootGroup);

            appendChildren($rootGroup, $groupsCollection);

            return $response->withJson($rootGroup);
        });
    }

    public function getGroupMiddleware()
    {
        return [
            new TokenCheck(
                $this->app->getContainer()
            )
        ];
    }
}
