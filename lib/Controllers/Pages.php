<?php

namespace Frontender\Core\Controllers;

use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Query;

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
                    $value = ['$regex' => $value, '$options' => 'i'];
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
                '$project' => [
                    '_id' => '$_id',
                    'uuid' => '$uuid',
                    'revision' => '$revision',
                    'definition' => '$definition',
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
                                    '$eq' => [['$type' => '$definition.template_config.model.controls.id.value'], 'string']
                                ],
                                'then' => [
                                    '$cond' => [
                                        'if' => [
                                            '$eq' => [['$substr' => ['$definition.template_config.model.controls.id.value', 0, 1]], '{']
                                        ],
                                        'then' => false,
                                        'else' => true
                                    ]
                                ],
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
                '$sort' => [
                    'sortKey' => (int)$filter['direction']
                ]
            ],
            ['$match' => $findFilter]
        ];
        $total = count($this->adapter->collection($collection)->aggregate($aggrigation)->toArray());

        $aggrigation[] = ['$limit' => 8 + $skip];
        $aggrigation[] = ['$skip' => $skip];
        $revisions = $this->adapter->collection($collection)->aggregate($aggrigation)->toArray();

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
                                    '$eq' => [['$type' => '$definition.template_config.model.controls.id.value'], 'string']
                                ],
                                'then' => [
                                    '$cond' => [
                                        'if' => [
                                            '$eq' => [['$substr' => ['$definition.template_config.model.controls.id.value', 0, 1]], '{']
                                        ],
                                        'then' => false,
                                        'else' => true
                                    ]
                                ],
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

    public function actionAdd($item, $collection = 'pages')
    {
        unset($item['_id']);
        $item['revision']['hash'] = md5(json_encode($item['definition']));
        $item['devision']['date'] = gmdate('Y-m-d\TH:i:s\Z');

        return $this->adapter->collection($collection)->insertOne($item);
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


        $this->adapter->collection('pages.public')->deleteMany([
            'revision.lot' => $page->revision->lot
        ]);
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

                $this->adapter->collection('routes.static')->deleteMany([
                    'page_id' => $page_id
                ]);

                // TODO: Something to do with the domains has to come in here as well.
                $this->adapter->collection('routes.static')->insertOne([
                    'source' => implode('/', [$adapterName, $modelName, $modelId]),
                    'destination' => $route,
                    'page_id' => $page_id
                ]);
            }
        }

        return $result;
    }

    public function actionDelete($lot_id, $collection = 'public')
    {
        $collection = 'pages' . ($collection ? '.' . $collection : '');

        $this->adapter->collection($collection)->deleteOne([
            'revision.lot' => $lot_id
        ]);

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