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

class Teams extends CoreRoute
{
    protected $group = '/api/teams';

    use Authorizable;

    public function registerCreateRoutes()
    {
        $this->app->put('', function (Request $request, Response $response) {
            // Here we will create a new team.
            $team = $request->getParsedBodyParam('team');
            $team['users'] = (array)$team['users'];

            $team['users'] = array_map(function ($user) {
                return (int)$user;
            }, $team['users']);

            if (isset($team['parent_team_id'])) {
                $team['parent_team_id'] = new ObjectId($team['parent_team_id']);
            }

            Adapter::getInstance()->collection('teams')->insertOne($team);

            return $response->withStatus(200);
        });
    }

    public function registerReadRoutes()
    {
        parent::registerReadRoutes();

        $self = $this;

        $this->app->get('/user/{user_id}', function (Request $request, Response $response) use ($self) {
            // I have to check if the user requesting the groups is the logged in user, if so I will let it pass.
            $user = $self->app->getContainer()->get('token')->getToken()->getClaim('sub')->getValue();

            if ($user != $request->getAttribute('user_id')) {
                $response = $self->isAuthorized('manage-users', $request, $response);
            }

            $userTeams = Adapter::getInstance()->collection('teams')->find([
                'users' => (int)$request->getAttribute('user_id')
            ])->toArray();
            $userTeams = Adapter::getInstance()->toJSON($userTeams);

            $userTeams = array_map(function ($team) use ($self) {
                $self->appendChildren($team);
                $self->appendParents($team);

                return $team;
            }, $userTeams);

            return $response->withJson($userTeams);
        });

        $this->app->get('/{team_id}/children', function (Request $request, Response $response) use ($self) {
            $response = $self->isAuthorized('manage-users', $request, $response);

            $teams = Adapter::getInstance()->collection('teams')->find([
                'parent_team_id' => new ObjectId($request->getAttribute('team_id'))
            ])->toArray();
            $teams = Adapter::getInstance()->toJSON($teams, true);

            return $response->withJson($teams);
        });

        $this->app->get('/{team_id}', function (Request $request, Response $response) use ($self) {
            $response = $self->isAuthorized('manage-users', $request, $response);

            $team = Adapter::getInstance()->collection('teams')->findOne([
                '_id' => new ObjectId($request->getAttribute('team_id'))
            ]);
            $team = Adapter::getInstance()->toJSON($team);
            $self->appendParents($team);

            return $response->withJson(
                $team
            );
        });

        $this->app->get('', function (Request $request, Response $response) use ($self) {
            $response = $self->isAuthorized('manage-users', $request, $response);

            $teamsCollection = Adapter::getInstance()->collection('teams');

            if ($request->getQueryParam('user')) {
                // I don't need to have the entire tree, Frontender will render that for us.
                // So I only need to teams to which I am directly connected.
                $rootTeam = $teamsCollection->find([
                    'users' => $request->getQueryParam('user')
                ])->find()->toArray();
                $rootTeam = Adapter::getInstance()->toJSON($rootTeam);

                $self->appendParents($rootTeam);
            } else {
                $rootTeam = $teamsCollection->find([
                    'parent_team_id' => ['$exists' => false]
                ])->toArray();
                $rootTeam = array_shift($rootTeam);
                $rootTeam = Adapter::getInstance()->toJSON($rootTeam);

                $self->appendChildren($rootTeam);
                $self->appendParents($rootTeam);
            }

            return $response->withJson($rootTeam);
        });
    }

    public function registerUpdateRoutes()
    {
        $self = $this;

        $this->app->post('', function (Request $request, Response $response) use ($self) {
            $response = $self->isAuthorized('manage-users', $request, $response);

            $body = $request->getParsedBody();
            $collection = Adapter::getInstance()->collection('teams');
            $teams = $collection->find([
                'users' => (int)$body['user']
            ])->toArray();

            foreach ($teams as $team) {
                $team = Adapter::getInstance()->toJSON($team, true);

                if (isset($team['users'])) {
                    $team['users'] = (array)$team['users'];
                }

                $collection->updateOne([
                    '_id' => new ObjectId($team['_id'])
                ], [
                    '$set' => [
                        'users' => array_diff($team['users'], [$body['user']])
                    ]
                ]);
            }

            // Get the teams that have been send.
            foreach ($body['team'] as $teamID) {
                $team = $collection->findOne([
                    '_id' => new ObjectId($teamID)
                ]);

                $team['users'][] = (int)$body['user'];

                $collection->updateOne([
                    '_id' => $team['_id']
                ], [
                    '$set' => [
                        'users' => (array)$team['users']
                    ]
                ]);
            }

            // Get all the teams that have the current userID in it.
            return $response->withStatus(200);
        });

        $this->app->post('/user/{user_id}', function (Request $request, Response $response) use ($self) {
            $user = $self->app->getContainer()->get('token')->getToken()->getClaim('sub')->getValue();
            $userID = $request->getAttribute('user_id');

            if ($user != $userID) {
                $response = $self->isAuthorized('manage-users', $request, $response);
            }

            $teams = $request->getParsedBodyParam('team');

            if (!is_array($teams)) {
                $teams = [$teams];
            }

            Adapter::getInstance()->collection('teams')->updateMany([
                'users' => (int)$userID
            ], [
                '$pull' => [
                    'users' => (int)$userID
                ]
            ]);

            // Get all the teams
            foreach ($teams as $team) {
                Adapter::getInstance()->collection('teams')->updateOne([
                    '_id' => new ObjectId($team)
                ], [
                    '$push' => [
                        'users' => (int)$userID
                    ]
                ]);
            }

            return $response->withStatus(200);
        });

        $this->app->post('/{team_id}', function (Request $request, Response $response) use ($self) {
            $response = $self->isAuthorized('manage-users', $request, $response);

            $body = $request->getParsedBody();
            $team = $body['team'];

            unset($team['_id']);
            if ($team['parent_team_id'] && !($team['parent_team_id'] instanceof ObjectId)) {
                $team['parent_team_id'] = new ObjectId($team['parent_team_id']);
            }

            if (isset($team['users'])) {
                $team['users'] = (array)$team['users'];
            }

            Adapter::getInstance()->collection('teams')->updateOne([
                '_id' => new ObjectId($request->getAttribute('team_id'))
            ], [
                '$set' => $team
            ]);

            return $response->withStatus(200);
        });
    }

    public function registerDeleteRoutes()
    {
        parent::registerDeleteRoutes();

        $self = $this;

        $this->app->delete('/{team_id}', function (Request $request, Response $response) use ($self) {
            $response = $self->isAuthorized('manage-users', $request, $response);

            $teamsToBeRemoved = [];
            $teamsCollection = Adapter::getInstance()->collection('teams');
            $lotsCollection = Adapter::getInstance()->collection('lots');

            function getChildteams($team, &$collection, $teamsCollection)
            {
                $collection[] = $team;

                $children = $teamsCollection->find([
                    'parent_team_id' => new ObjectId($team)
                ])->toArray();

                foreach ($children as $child) {
                    getChildteams($child->_id->__toString(), $collection, $teamsCollection);
                }
            };

            getChildteams($request->getAttribute('team_id'), $teamsToBeRemoved, $teamsCollection);

            // Get the original team.
            $team = Adapter::getInstance()->collection('teams')->findOne([
                '_id' => new ObjectId($request->getAttribute('team_id'))
            ]);

            if (!isset($team->parent_team_id) || empty($team->parent_team_id)) {
                return $response->withStatus(200);
            }

            foreach ($teamsToBeRemoved as $team) {
                // Find all the lots that belong to this team.
                $lotsCollection->updateMany([
                    'teams' => $team
                ], [
                    '$pull' => [
                        'teams' => $team
                    ]
                ]);
                $teamsCollection->deleteOne([
                    '_id' => new ObjectId($team)
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

    public function appendParents(&$team)
    {
        if (isset($team->parent_team_id) && !empty($team->parent_team_id)) {
            $parent = Adapter::getInstance()->collection('teams')->findOne([
                '_id' => new ObjectId($team->parent_team_id)
            ]);
            $parent = Adapter::getInstance()->toJSON($parent);

            $team->parent = $parent;

            $this->appendParents($team->parent);
        }
    }

    public function appendChildren(&$team)
    {
        $self = $this;
        $children = Adapter::getInstance()->collection('teams')->find([
            'parent_team_id' => new ObjectId($team->_id)
        ])->toArray();
        $children = Adapter::getInstance()->toJSON($children);

        if ($children) {
            $team->children = array_map(function ($team) use ($self) {
                $self->appendChildren($team);
                $self->appendParents($team);

                return $team;
            }, $children);
        }
    }
}
