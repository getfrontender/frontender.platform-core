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

namespace Frontender\Core\Controllers;

use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Query;
use Frontender\Core\DB\Adapter;

class Pages extends Core
{
    public function actionBrowse($filter = [])
    {
        $collection = isset($filter['collection']) ? 'pages.' . $filter['collection'] : 'pages';
        $findFilter = new \stdClass();
        $skip = 0;

        if (isset($filter['skip'])) {
            $skip = $filter['skip'];
            unset($filter['skip']);
        }

        if (isset($filter['lot'])) {
            $findFilter->{'revision.lot'} = $filter['lot'];
        } else if (isset($filter['filter']) && is_array($filter['filter'])) {
            foreach ($filter['filter'] as $key => $value) {
                if ($value === 'true') {
                    $value = ['$eq' => true];
                } else if ($value === 'false') {
                    $value = ['$eq' => false];
                }

                if (!is_numeric($value) && is_string($value)) {
                    // We will filter strings as a regex and case insensitive.
                    $value = ['$regex' => '.*' . $value . '.*', '$options' => 'i'];
                }

                $findFilter->{$key} = $value;
            }
        }

        $aggrigation = [
            [
                '$sort' => [
                    'revision.date' => -1
                ]
            ],
            [
                '$group' => [
                    '_id' => '$revision.lot',
                    'uuid' => [
                        '$first' => '$_id'
                    ],
                    'revision' => [
                        '$first' => '$revision'
                    ],
                    'definition' => [
                        '$first' => '$definition'
                    ]
                ]
            ],
            [
                '$lookup' => [
                    'from' => 'pages.public',
                    'localField' => 'revision.lot',
                    'foreignField' => 'revision.lot',
                    'as' => 'publishedPage'
                ]
            ],
            [
                '$lookup' => [
                    'from' => 'routes',
                    'localField' => 'revision.lot',
                    'foreignField' => 'page_lot',
                    'as' => 'foundRoute'
                ]
            ],
            [
                '$addFields' => [
                    'lotID' => [
                        '$toObjectId' => '$revision.lot'
                    ]
                ]
            ],
            [
                '$lookup' => [
                    'from' => 'lots',
                    'localField' => 'lotID',
                    'foreignField' => '_id',
                    'as' => 'lot'
                ]
            ],
            [
                '$project' => [
                    '_id' => '$_id',
                    'uuid' => '$uuid',
                    'revision' => '$revision',
                    'definition' => '$definition',
                    'foundRoute' => '$foundRoute',
                    'lot' => [
                        '$arrayElemAt' => ['$lot', 0]
                    ],
                    'sortKey' => [
                        '$cond' => [
                            'if' => [
                                '$eq' => [['$type' => '$' . $filter['sort']], 'object']
                            ],
                            'then' => '$' . $filter['sort'] . '.' . $filter['locale'],
                            'else' => '$' . $filter['sort']
                        ]
                    ],
                    'states' => [
                        // This is true when one revision in this lot is published.
                        'isPublished' => [
                            '$cond' => [
                                'if' => [
                                    '$gt' => [['$size' => '$publishedPage'], 0]
                                ],
                                'then' => true,
                                'else' => false
                            ]
                        ],
                        // This is true when the current revision is published.
                        'isPublic' => [
                            '$cond' => [
                                'if' => [
                                    '$eq' => [['$arrayElemAt' => ['$publishedPage.revision.hash', 0]], '$revision.hash']
                                ],
                                'then' => true,
                                'else' => false
                            ]
                        ],
                        'isRoute' => [
                            '$cond' => [
                                'if' => [
                                    '$gt' => [['$size' => '$foundRoute'], 0]
                                ],
                                'then' => true,
                                'else' => false
                            ]
                        ],
                        'isRoot' => [
                            '$cond' => [
                                'if' => [
                                    '$eq' => ['$definition.route.' . $filter['locale'], '/']
                                ],
                                'then' => true,
                                'else' => false
                            ]
                        ]
                    ]
                ]
            ],
            [
                '$match' => [
                    'lot.teams' => [
                        '$in' => array_map(function ($team) {
                            return $team->_id->__toString();
                        }, $filter['teams'])
                    ]
                ]
            ],
            [
                '$sort' => [
                    'sortKey' => (int)$filter['direction']
                ]
            ],
            ['$match' => $findFilter]
        ];

        $total = count($this->adapter->collection($collection)->aggregate($aggrigation, [
            'allowDiskUse' => true
        ])->toArray());

        $aggrigation[] = ['$limit' => 8 + $skip];
        $aggrigation[] = ['$skip' => $skip];
        $revisions = $this->adapter->collection($collection)->aggregate($aggrigation, [
            'allowDiskUse' => true
        ])->toArray();

        return [
            'total' => $total,
            'items' => array_map(function ($revision) {
                $revision['_id'] = $revision['uuid'];
                unset($revision['uuid']);
                // unset( $revision['sortKey'] );

                return $revision;
            }, $revisions)
        ];
    }

    public function actionRead($id)
    {
        $revisions = $this->adapter->collection('pages')->aggregate([
            [
                '$lookup' => [
                    'from' => 'pages.public',
                    'localField' => 'revision.lot',
                    'foreignField' => 'revision.lot',
                    'as' => 'publishedPage'
                ]
            ],
            [
                '$lookup' => [
                    'from' => 'routes',
                    'localField' => 'revision.lot',
                    'foreignField' => 'page_lot',
                    'as' => 'foundRoute'
                ]
            ],
            [
                '$project' => [
                    '_id' => '$_id',
                    'uuid' => '$uuid',
                    'revision' => '$revision',
                    'definition' => '$definition',
                    'states' => [
                        'isPublished' => [
                            '$cond' => [
                                'if' => [
                                    '$gt' => [['$size' => '$publishedPage'], 0]
                                ],
                                'then' => true,
                                'else' => false
                            ]
                        ],
                        'isPublic' => [
                            '$cond' => [
                                'if' => [
                                    '$eq' => [['$arrayElemAt' => ['$publishedPage.revision.hash', 0]], '$revision.hash']
                                ],
                                'then' => true,
                                'else' => false
                            ]
                        ],
                        'isRoute' => [
                            '$cond' => [
                                'if' => [
                                    '$gt' => [['$size' => '$foundRoute'], 0]
                                ],
                                'then' => true,
                                'else' => false
                            ]
                        ],
                        'isRoot' => [
                            '$cond' => [
                                'if' => [
                                    '$eq' => ['$definition.route.' . $_GET['locale'], '/']
                                ],
                                'then' => true,
                                'else' => false
                            ]
                        ]
                    ]
                ]
            ],
            ['$match' => [
                '_id' => new ObjectId($id)
            ]]
        ])->toArray();

        return array_shift($revisions);
    }

    public function actionEdit($id, $data)
    {
        unset($data['_id']);

        $data['revision']['hash'] = md5(json_encode($data['definition']));

        $data = $this->adapter->collection('pages')->findOneAndReplace([
            'revision.lot' => $id
        ], $data, [
            'returnNewDocument' => true,
            'upsert' => true
        ]);

        return $data;
    }

    public function actionAdd($page, $collection = 'pages')
    {
        if (!isset($page['team'])) {
            // We need to retreive the first team, this is always the team that is the Root.
            $team = Adapter::getInstance()->collection('teams')->findOne();
            $page['team'] = $team['_id']->__toString();
        }

        if (isset($page['team'])) {
            $team = $page['team'];
            unset($page['team']);

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
                        'startWith' => '$parent_team_id',
                        'connectFromField' => 'parent_team_id',
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

            $lot = Adapter::getInstance()->collection('lots')->insertOne([
                'teams' => array_map(function ($team) {
                    return $team->__toString();
                }, $teams),
                'created' => [
                    'date' => time()
                ]
            ]);

            $page['revision']['lot'] = $lot->getInsertedId()->__toString();
        }

        unset($page['_id']);
        $page['revision']['hash'] = md5(json_encode($page['definition']));
        $page['devision']['date'] = gmdate('Y-m-d\TH:i:s\Z');

        return $this->adapter->collection($collection)->insertOne($page);
    }

    public function actionSanitize($pageJson)
    {
        $this->_sanitizeConfig($pageJson);

        return $pageJson;
    }

    public function actionPublish($page)
    {
        unset($page->_id);

        $page->definition = json_decode(json_encode($page->definition), true);
        $page->definition = $this->actionSanitize($page->definition);

        $this->actionDelete($page->revision->lot);

        $result = $this->adapter->collection('pages.public')->insertOne($page);

        // If the template_config has a model name and id set then we can create a static reroute in the system.
        // I will append the page_id to it so we can remove it when there is an update or when we remove the public page.
        // This only has to happen here, because I don't care about all the other pages in the system.

        $modelName = array_reduce(['template_config', 'model', 'data', 'model'], function ($carry, $key) {
            if (!isset($carry[$key]) || !$carry) {
                return false;
            }

            return $carry[$key];
        }, $page->definition);
        $adapterName = array_reduce(['template_config', 'model', 'data', 'adapter'], function ($carry, $key) {
            if (!isset($carry[$key]) || !$carry) {
                return false;
            }

            return $carry[$key];
        }, $page->definition);
        $modelId = array_reduce(['template_config', 'model', 'data', 'id'], function ($carry, $key) {
            if (!isset($carry[$key]) || !$carry) {
                return false;
            }

            return $carry[$key];
        }, $page->definition);

        if ($adapterName && $modelName && $modelId && ($page->definition['route'] || $page->defintion['cononical'])) {
            if (strpos(trim($modelId), '{') === false) {
                // We prefer the cononical
                $route = $page->definition['route'] ?? $page->definition['cononical'];

                $page_id = '';
                try {
                    $page_id = $result->getInsertedId()->__toString();
                } catch (\Error $e) {
                    $page_id = $result->_id->__toString();
                }

                $this->adapter->collection('routes')->insertOne([
                    'resource' => implode('/', [$adapterName, $modelName, $modelId]),
                    'destination' => $route,
                    'page_id' => $page_id,
                    'page_lot' => $page->revision->lot,
                    'type' => 'landingpage',
                    'status' => 302
                ]);
            }
        }


        return $result;
    }

    public function actionDelete($lot_id, $collection = 'public')
    {
        $collection = 'pages' . ($collection ? '.' . $collection : '');

        $page = $this->adapter->collection($collection)->findOne([
            'revision.lot' => $lot_id
        ]);

        if ($page) {
            $this->adapter->collection('routes')->deleteMany([
                'page_id' => $page->_id->__toString()
            ]);

            $this->adapter->collection($collection)->deleteOne([
                'revision.lot' => $lot_id
            ]);
        }

        return true;
    }

    private function _sanitizeConfig(&$container)
    {
        if (isset($container['template_config'])) {
            $newConfig = [];

            foreach ($container['template_config'] as $key => $section) {
                $newSection = [];

                if (isset($section['controls'])) {
                    foreach ($section['controls'] as $index => $control) {
                        if (isset($control['value'])) {
                            $newSection[$index] = $control['value'];
                        }
                    }

                    $newConfig[$key] = $newSection;
                }
            }

            $container['template_config'] = $newConfig;
        }

        if (isset($container['containers'])) {
            foreach ($container['containers'] as &$subContainer) {
                $this->_sanitizeConfig($subContainer);
            }
        }
    }
}
