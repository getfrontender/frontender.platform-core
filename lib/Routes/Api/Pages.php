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

use Frontender\Core\Controllers\Pages\Revisions;
use Frontender\Core\DB\Adapter;
use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Slim\Http\Request;
use Slim\Http\Response;
use Frontender\Core\Routes\Middleware\TokenCheck;

class Pages extends CoreRoute
{
    protected $group = '/api/pages';

    use Authorizable;

    protected function registerCreateRoutes()
    {
        parent::registerCreateRoutes(); // TODO: Change the autogenerated stub

        $this->app->put('/revision', function (Request $request, Response $response) {
            $json = $request->getParsedBody();
            $result = \Frontender\Core\Controllers\Pages::add($json);
            $json['_id'] = $result->getInsertedId()->__toString();

            return $response->withStatus(200)
                ->withJson($json);
        });

        $this->app->put('/{lot_id}/revision', function (Request $request, Response $response) {
            $json = $request->getParsedBody();
            $team = $json['team'];
            $json = $json['page'];
            $teams = [];

            if (isset($this->token->getClaim('user')->id)) {
                $json['revision']['user']['id'] = $this->token->getClaim('user')->id;
            }

            if (isset($this->token->getClaim('user')->name)) {
                $json['revision']['user']['name'] = $this->token->getClaim('user')->name;
            }

            if (isset($group)) {
                // I will get the teams and from there I will get the parents.
                $teamWithParents = Adapter::getInstance()->collection('teams')->aggregate([
                    [
                        '$match' => [
                            '_id' => new ObjectId($team)
                        ]
                    ],
                    [
                        '$graphLookup' => [
                            'from' => 'teams',
                            'startWith' => '$parent_group_id',
                            'connectFromField' => 'parent_group_id',
                            'connectToField' => '_id',
                            'as' => 'parents'
                        ]
                    ]
                ])->toArray();
                $teamWithParents = array_shift($teamWithParents);

                $teams[] = $teamWithParents->_id;

                foreach ($teamWithParents->parents as $parent) {
                    $teams[] = $parent->_id;
                }

                Adapter::getInstance()->collection('lots')->updateOne([
                    '_id' => new ObjectId($request->getAttribute('lot_id'))
                ], [
                    '$set' => [
                        'teams' => array_map(function ($team) {
                            return $group->__toString();
                        }, $teams)
                    ]
                ]);
            }

            $json = Revisions::add($json);

            return $response->withJson($json);
        });

        $this->app->put('/{lot_id}/trash', function (Request $request, Response $response) {
            // Remove the published page, and move all revisions to the trash.
            \Frontender\Core\Controllers\Pages::delete($request->getAttribute('lot_id'), 'public');
            Revisions::delete($request->getAttribute('lot_id'));

            return $response->withStatus(200);
        });

        $this->app->put('/{page_id}/public', function (Request $request, Response $response) {
            $page = Adapter::getInstance()->toJSON(
                \Frontender\Core\Controllers\Pages::read($request->getAttribute('page_id'))
            );

            \Frontender\Core\Controllers\Pages::publish($page);

            return $response->withStatus(200);
        });

        $this->app->put('', function (Request $request, Response $response) {
            // We need to create a lot here. But we will also receive the group that is selected.
            // For the group we need all the parents.
            $body = $request->getParsedBody();
            $teams = [];

            if (isset($body['group'])) {
                // I will get the teams and from there I will get the parents.
                $teamWithParents = Adapter::getInstance()->collection('teams')->aggregate([
                    [
                        '$match' => [
                            '_id' => new ObjectId($body['group'])
                        ]
                    ],
                    [
                        '$graphLookup' => [
                            'from' => 'teams',
                            'startWith' => '$parent_group_id',
                            'connectFromField' => 'parent_group_id',
                            'connectToField' => '_id',
                            'as' => 'parents'
                        ]
                    ]
                ])->toArray();
                $groupWithParents = array_shift($teamWithParents);

                $teams[] = $teamWithParents->_id;

                foreach ($teamWithParents->parents as $parent) {
                    $teams[] = $parent->_id;
                }
            }

            $lot = Adapter::getInstance()->collection('lots')->insertOne([
                'teams' => array_map(function ($group) {
                    return $group->__toString();
                }, $teams),
                'created' => [
                    'date' => new UTCDateTime(),
                    'user' => $this->token->getClaim('user')->id
                ]
            ]);

            $body['page']['revision']['lot'] = $lot->getInsertedId()->__toString();

            $result = Adapter::getInstance()->collection('pages')->insertOne($body['page'], [
                'upsert' => true,
                'returnNewDocument' => true
            ]);

            $page = Adapter::getInstance()->toJSON(
                Adapter::getInstance()->collection('pages')->findOne([
                    '_id' => new ObjectId($result->getInsertedId()->__toString())
                ])
            );

            return $response->withJson($page);
        });
    }

    protected function registerReadRoutes()
    {
        parent::registerReadRoutes();

        $self = $this;

        $this->app->get('', function (Request $request, Response $response) use ($self) {
            $filter = $request->getParsedBodyParam('filter');
            $filter = $self->appendProxyPathToFilter($filter, $request);

            $json = \Frontender\Core\Controllers\Pages::browse([
                'collection' => $request->getQueryParam('collection'),
                'lot' => $request->getQueryParam('lot'),
                'sort' => !empty($request->getQueryParam('sort')) ? $request->getQueryParam('sort') : 'definition.name',
                'direction' => !empty($request->getQueryParam('direction')) ? $request->getQueryParam('direction') : 1,
                'locale' => !empty($request->getQueryParam('locale')) ? $request->getQueryParam('locale') : 'en-GB',
                'filter' => $filter,
                'skip' => (int)$request->getQueryParam('skip')
            ]);

            $json['items'] = Adapter::getInstance()->toJSON($json['items']);

            return $response->withJson($json);
        });

        $this->app->get('/public', function (Request $request, Response $response) {
            try {
                $json = \Frontender\Core\Controllers\Pages::browse([
                    'collection' => 'public',
                    'sort' => !empty($request->getQueryParam('sort')) ? $request->getQueryParam('sort') : 'definition.name',
                    'direction' => !empty($request->getQueryParam('direction')) ? $request->getQueryParam('direction') : 1,
                    'locale' => !empty($request->getQueryParam('locale')) ? $request->getQueryParam('locale') : 'en-GB'
                ]);
                $json = Adapter::getInstance()->toJSON($json['items']);
            } catch (\Exception $e) {
                $json = [];
            }

            return $response->withJson($json);
        });

        $this->app->get('/{page_id}', function (Request $request, Response $response) {
            $json = Adapter::getInstance()->toJSON(
                \Frontender\Core\Controllers\Pages::read($request->getAttribute('page_id'))
            );

            return $response->withJson($json);
        });

        $this->app->get('/{page_id}/preview', function (Request $request, Response $response) {
            $json = Adapter::getInstance()->toJSON(
                \Frontender\Core\Controllers\Pages::read($request->getAttribute('page_id'))
            );
            $json = json_decode(json_encode($json), true);
            $json = \Frontender\Core\Controllers\Pages::sanitize($json['definition']);

            try {
                $page = $this->page;
                $this->language->set($request->getQueryParam('locale'));
                $page->setData($json);
                $page->setRequest($request);
                $page->parseData();

                $response->getBody()->write($page->render());
            } catch (\Exception $e) {
                echo $e->getMessage();
                echo '<pre>';
                print_r(array_map(function ($trace) {
                    if (isset($trace['file']) && isset($trace['line'])) {
                        return $trace['file'] . '::' . $trace['line'];
                    }

                    return '';
                }, $e->getTrace()));
                echo '</pre>';
                die();
            } catch (\Error $e) {
                echo $e->getMessage();
                echo '<pre>';
                print_r(array_map(function ($trace) {
                    if (isset($trace['file']) && isset($trace['line'])) {
                        return $trace['file'] . '::' . $trace['line'];
                    }

                    return '';
                }, $e->getTrace()));
                echo '</pre>';
                die();
            }

            return $response;
        });

        $this->app->get('/{lot_id}/public', function (Request $request, Response $response) {
            $json = Adapter::getInstance()->toJSON(
                Revisions::read($request->getAttribute('lot_id'), 'public')
            );

            return $response->withJson($json);
        });

        $this->app->get('/{lot_id}/revision', function (Request $request, Response $response) {
            $json = Adapter::getInstance()->toJSON(
                Revisions::read($request->getAttribute('lot_id'), $request->getQueryParam('revision', 'last'))
            );

            return $response->withJson($json);
        });

        $this->app->get('/{lot_id}/revisions', function (Request $request, Response $response) {
            $json = Adapter::getInstance()->toJSON(
                Revisions::read($request->getAttribute('lot_id'), 'all', -1)
            );

            return $response->withJson($json);
        });

        $this->app->get('/{lot_id}/trash', function (Request $request, Response $response) {
            $json = Adapter::getInstance()->toJSON(
                \Frontender\Core\Controllers\Pages::browse([
                    'collection' => 'trash',
                    'lot' => $request->getAttribute('lot_id'),
                    'sort' => !empty($request->getQueryParam('sort')) ? $request->getQueryParam('sort') : 'definition.name',
                    'direction' => !empty($request->getQueryParam('direction')) ? $request->getQueryParam('direction') : 1,
                    'locale' => !empty($request->getQueryParam('locale')) ? $request->getQueryParam('locale') : 'en-GB'
                ])
            );

            return $response->withJson($json);
        });
    }

    protected function registerUpdateRoutes()
    {
        parent::registerUpdateRoutes();

        $self = $this;

        // New url for the pages endpoint.
        $this->app->post('', function (Request $request, Response $response) use ($self) {
            // $self->isAuthorized('space-administrator', $request, $response);

            $filter = $request->getParsedBodyParam('filter');
            $filter = $self->appendProxyPathToFilter($filter, $request);

            $json = \Frontender\Core\Controllers\Pages::browse([
                'collection' => $request->getParsedBodyParam('collection'),
                'lot' => $request->getParsedBodyParam('lot'),
                'sort' => !empty($request->getParsedBodyParam('sort')) ? $request->getParsedBodyParam('sort') : 'definition.name',
                'direction' => !empty($request->getParsedBodyParam('direction')) ? $request->getParsedBodyParam('direction') : 1,
                'locale' => !empty($request->getParsedBodyParam('locale')) ? $request->getParsedBodyParam('locale') : 'en-GB',
                'filter' => $filter,
                'skip' => (int)$request->getParsedBodyParam('skip'),
                'teams' => Adapter::getInstance()->collection('teams')->find([
                    'users' => (int)$this->token->getClaim('sub')
                ])->toArray()
            ]);

            $json['items'] = Adapter::getInstance()->toJSON($json['items']);

            return $response->withJson($json);
        });

        $this->app->post('/{lot_id}', function (Request $request, Response $response) {
            $data = \Frontender\Core\Controllers\Pages::update($request->getAttribute('lot_id'), $request->getParsedBody());
        });

        $this->app->post('/{page_id}/update', function (Request $request, Response $response) {
            $data = $request->getParsedBody();
            unset($data['_id']);

            Adapter::getInstance()->collection('pages')->updateOne([
                '_id' => new ObjectId($request->getAttribute('page_id'))
            ], [
                '$set' => $data
            ]);

            return $response->withStatus(200);
        });

        $this->app->post('/{page_id}/preview', function (Request $request, Response $response) {
            $body = $request->getParsedBody();
            $json = json_decode($body['data'], true);
            $json = \Frontender\Core\Controllers\Pages::sanitize($json['definition']);

            try {
                $page = $this->page;
                $this->language->set($request->getQueryParam('locale'));
                $page->setData($json);
                $page->setRequest($request);
                $page->parseData();

                $response->getBody()->write($page->render());
                $response = $response->withHeader('X-XSS-Protection', '0');
            } catch (\Exception $e) { } catch (\Error $e) { }

            return $response;
        });
    }

    public function registerDeleteRoutes()
    {
        parent::registerDeleteRoutes();

        $this->app->delete('/{lot_id}/public', function (Request $request, Response $response) {
            \Frontender\Core\Controllers\Pages::delete($request->getAttribute('lot_id'), 'public');

            return $response->withStatus(200);
        });

        $this->app->delete('/{lot_id}', function (Request $request, Response $response) {
            \Frontender\Core\Controllers\Pages::delete($request->getAttribute('lot_id'), '');

            return $response->withStatus(200);
        });
    }

    public function getGroupMiddleware()
    {
        return [
            new TokenCheck(
                $this->app->getContainer(),
                [
                    'exclude' => [
                        '/api/pages/{page_id}/preview'
                    ]
                ]
            )
        ];
    }

    private function appendProxyPathToFilter($filter, Request $request)
    {
        $uri = $request->getUri();
        $locale = $request->getQueryParam('locale') ?? 'en-GB';
        $settings = Adapter::getInstance()->collection('settings')->find()->toArray();
        $settings = Adapter::getInstance()->toJSON($settings);
        $settings = array_shift($settings);
        $scopes = array_filter($settings->scopes, function ($scope) use ($uri, $locale) {
            return $scope->domain === $uri->getHost() && $scope->locale === $locale;
        });
        $scope = array_shift($scopes);

        if (isset($scope->proxy_path)) {
            // If there is an $or in the filter, we have a query, else we don't and apply the normal filter.
            if (isset($filter['$or'])) {
                $routeFilter = null;

                foreach ($filter['$or'] as $key => &$filterItem) {
                    $firstKey = array_keys($filterItem);
                    $firstKey = array_shift($firstKey);

                    if ($firstKey === 'definition.route.' . $locale) {
                        $routeFilter = &$filterItem[$firstKey];
                        break;
                    }
                }

                if ($routeFilter) {
                    // Prepend the proxy path to the regex.
                    $path = ltrim($scope->proxy_path, '/');
                    $routeFilter['$regex'] = implode('/', [$path, $routeFilter['$regex']]);
                }
            } else {
                // Append a filter.
                $filter = [
                    'definition.route.' . $locale => [
                        '$regex' => ltrim($scope->proxy_path, '/') . '.*',
                        '$options' => 'i'
                    ]
                ];
            }
        }

        return $filter;
    }
}
