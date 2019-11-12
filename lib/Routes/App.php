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

namespace Frontender\Core\Routes;

use Frontender\Core\DB\Adapter;
use Frontender\Core\Routes\Helpers\CoreRoute;
use Slim\Http\Request;
use Slim\Http\Response;

class App extends CoreRoute
{
    protected function registerReadRoutes()
    {
        parent::registerReadRoutes();

        // $this->app->get('/', function (Request $request, Response $response) {
        //     return $response->withRedirect('/en');
        // });

        $this->app->get('/{locale}', function (Request $request, Response $response) {
            // Add fallback language here.
            $data = Adapter::getInstance()->toJSON($request->getAttribute('json'), true);

            $page = $this->page;
            $page->setParameters([
                'locale' => $this->language->get(),
                'debug' => $this->settings['debug'],
                'query' => $request->getQueryParams()
            ]);
            $page->setData($data['definition']);
            $page->setRequest($request);

            $response->getBody()->write($page->render());

            return $response;
        })->setName('home');

        $this->app->get('/{locale}/{page:.*}/{slug:.*}' . $this->config->id_separator . '{id}', function (Request $request, Response $response) {
            $attributes = $request->getAttributes();

            $data = Adapter::getInstance()->toJSON($request->getAttribute('json'), true);

            $page = $this->page;
            $page->setName($attributes['page']);
            $page->setParameters([
                'page' => $request->getAttribute('page'),
                'locale' => $this->language->get(),
                'id' => $attributes['id'],
                'debug' => $this->settings['debug'],
                'query' => $request->getQueryParams()
            ]);
            $page->setData($data['definition']);
            $page->setRequest($request);

            $page->parseData();

            $response->getBody()->write($page->render());

            return $response;
        })->setName('details');

        $this->app->get('/{locale}/partial', function (Request $request, Response $response) {
            $query = $request->getQueryParams();

			// I know that the states will be in need of parsing.
			// This needs to be done here.
            $states = [];
            $config = [];
            if (array_key_exists('config', $query) && $query['config']) {
                $conf = json_decode($query['config']);
                if ($conf) {
                    foreach ($conf as $key => $values) {
                        $config[$key] = [];
                        foreach ($values as $name => $value) {
                            $config[$key][$name] = $value;
                        }
                    }
                }
            }
            foreach ($query as $key => $value) {
                $copy = $value;
                try {
                    $value = json_decode($copy, true);
                    if (!$value) {
                        $value = $copy;
                    }
                } catch (Error $e) {
					// NOOP
                } catch (Exception $e) {
					// NOOP
                }
                $states[$key] = $value;
            }

            if (isset($query['model']) && !empty($query['model'])) {
                $states['data'] = $states['data'] ?? [];

                $states['data']['model'] = $query['model'];
                unset($query['model']);
            }

            if (isset($query['adapter']) && !empty($query['adapter'])) {
                $states['data'] = $states['data'] ?? [];

                $states['data']['adapter'] = $query['adapter'];
                unset($query['adapter']);
            }

            if (isset($query['id']) && !empty($query['id'])) {
                $states['data'] = $states['data'] ?? [];

                $states['data']['id'] = $query['id'];
                unset($query['id']);
            }

            $data = [
                'template' => $query['layout'],
                'template_config' => [
                    'model' => $states
                ]
            ];
            $data['template_config'] = array_merge($data['template_config'], $config);

            $clone = json_decode(json_encode($data), true);

            $data['template_config']['container'] = $clone;

            $page = $this->page;
            $page->setParameters([
                'page' => $request->getAttribute('page'),
                'locale' => $this->language->get(),
                'debug' => $this->settings['debug'],
                'query' => $request->getQueryParams()
            ]);
            $page->setData($data);
            $page->setRequest($request);
            $response->getBody()->write($page->render());
            return $response;
        })->setName('partial');

        $this->app->get('/{locale}/{page:.*}', function (Request $request, Response $response) {
            $data = Adapter::getInstance()->toJSON($request->getAttribute('json'), true);

            $page = $this->page;
            $page->setParameters([
                'page' => $request->getAttribute('page'),
                'locale' => $this->language->get(),
                'debug' => $this->settings['debug'],
                'query' => $request->getQueryParams()
            ]);
            $page->setData($data['definition']);
            $page->setRequest($request);
            $response->getBody()->write($page->render());

            return $response;
        })->setName('list');
    }
}