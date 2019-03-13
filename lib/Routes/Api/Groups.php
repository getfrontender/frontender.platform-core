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

    public function registerCreateRoutes()
    {
        $this->app->put('', function (Request $request, Response $response) {
            // Here we will create a new group.
            $group = $request->getParsedBodyParam('group');
            $group['users'] = (array)$group['users'];

            $group['users'] = array_map(function ($user) {
                return (int)$user;
            }, $group['users']);

            if (isset($group['parent_group_id'])) {
                $group['parent_group_id'] = new ObjectId($group['parent_group_id']);
            }

            Adapter::getInstance()->collection('groups')->insertOne($group);

            return $response->withStatus(200);
        });
    }

    public function registerReadRoutes()
    {
        parent::registerReadRoutes();

        $self = $this;

        $this->app->get('/user/{user_id}', function (Request $request, Response $response) {
            $userGroups = Adapter::getInstance()->collection('groups')->find([
                'users' => (int)$request->getAttribute('user_id')
            ])->toArray();
            $userGroups = Adapter::getInstance()->toJSON($userGroups, true);

            return $response->withJson($userGroups);
        });

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

            if (isset($group['users'])) {
                $group['users'] = (array)$group['users'];
            }

            Adapter::getInstance()->collection('groups')->updateOne([
                '_id' => new ObjectId($request->getAttribute('group_id'))
            ], [
                '$set' => $group
            ]);

            return $response->withStatus(200);
        });
    }

    public function registerDeleteRoutes()
    {
        parent::registerDeleteRoutes();

        $this->app->delete('/{group_id}', function (Request $request, Response $response) {
            $groupsToBeRemoved = [];
            $groupsCollection = Adapter::getInstance()->collection('groups');
            $lotsCollection = Adapter::getInstance()->collection('lots');

            function getChildGroups($group, &$collection, $groupsCollection)
            {
                $collection[] = $group;

                $children = $groupsCollection->find([
                    'parent_group_id' => new ObjectId($group)
                ])->toArray();

                foreach ($children as $child) {
                    getChildGroups($child->_id->__toString(), $collection, $groupsCollection);
                }
            };

            getChildGroups($request->getAttribute('group_id'), $groupsToBeRemoved, $groupsCollection);


            foreach ($groupsToBeRemoved as $group) {
                // Find all the lots that belong to this group.
                $lotsCollection->updateMany([
                    'groups' => $group
                ], [
                    '$pull' => [
                        'groups' => $group
                    ]
                ]);
                $groupsCollection->deleteOne([
                    '_id' => new ObjectId($group)
                ]);
            }

            return $response->withStatus(204);
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
