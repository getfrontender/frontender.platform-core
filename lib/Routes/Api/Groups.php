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

        $this->app->get('/{group_id}', function (Request $request, Response $response) {
            $group = Adapter::getInstance()->collection('groups')->findOne([
                '_id' => new ObjectId($request->getAttribute('group_id'))
            ]);
            $group = Adapter::getInstance()->toJSON($group);

            return $response->withJson(
                $group
            );
        });

        $this->app->get('', function (Request $request, Response $response) use ($self) {
            // $self->isAuthorized('space-administrator', $request, $response);

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

            if ($request->getQueryParam('user')) {
                // I don't need to have the entire tree, Frontender will render that for us.
                // So I only need to groups to which I am directly connected.
                $rootGroup = $groupsCollection->find([
                    'users' => $request->getQueryParam('user')
                ])->find()->toArray();
            } else {
                $rootGroup = $groupsCollection->find([
                    'parent_group_id' => ['$exists' => false]
                ])->toArray();
                $rootGroup = array_shift($rootGroup);

                appendChildren($rootGroup, $groupsCollection);
            }

            return $response->withJson($rootGroup);
        });
    }

    public function registerUpdateRoutes()
    {
        $this->app->post('', function (Request $request, Response $response) {
            $body = $request->getParsedBody();
            $collection = Adapter::getInstance()->collection('groups');
            $groups = $collection->find([
                'users' => (int)$body['user']
            ])->toArray();

            foreach ($groups as $group) {
                $group = Adapter::getInstance()->toJSON($group, true);

                $collection->updateOne([
                    '_id' => new ObjectId($group['_id'])
                ], [
                    '$set' => [
                        'users' => array_diff($group['users'], [$body['user']])
                    ]
                ]);
            }

            // Get the groups that have been send.
            foreach ($body['groups'] as $groupID) {
                $group = $collection->findOne([
                    '_id' => new ObjectId($groupID)
                ]);

                $group['users'][] = (int)$body['user'];

                $collection->updateOne([
                    '_id' => $group['_id']
                ], [
                    '$set' => [
                        'users' => $group['users']
                    ]
                ]);
            }

            // Get all the groups that have the current userID in it.
            return $response->withStatus(200);
        });

        $this->app->post('/{group_id}', function (Request $request, Response $response) {
            $body = $request->getParsedBody();
            $group = $body['group'];

            unset($group['_id']);
            if ($group['parent_group_id'] && !($group['parent_group_id'] instanceof ObjectId)) {
                $group['parent_group_id'] = new ObjectId($group['parent_group_id']);
            }

            Adapter::getInstance()->collection('groups')->updateOne([
                '_id' => new ObjectId($request->getAttribute('group_id'))
            ], [
                '$set' => $group
            ]);

            return $response->withStatus(200);
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
