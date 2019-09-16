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

namespace Frontender\Core\Template\Helper;

use Frontender\Core\DB\Adapter;
use Slim\Container;
use Slim\Http\Uri;
use Doctrine\Common\Inflector\Inflector;
use Frontender\Core\Template\Filter\Translate;
use Frontender\Core\Utils\Scopes;

class Router extends \Twig_Extension
{
    public $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('route', [$this, 'route'])
        ];
    }

    public function route($params = [])
    {
        // If a url is found, we won't even look further
        if (isset($params['url'])) {
            return $params['url'];
        }

        $params['locale'] = $params['locale'] ?? $this->container->language->get('language');
        $params['slug'] = $params['slug'] ?? '';
        $fallbackLocale = $this->container['fallbackScope']['locale'];
        $uri = $this->container->get('request')->getUri();

        $path = $this->_getPath($params);
        if (is_object($path)) {
            $path = $path->{$params['locale']} ?? $path->{$fallbackLocale};
        } else if (is_array($path)) {
            $path = $path[$params['locale']] ?? $path[$fallbackLocale];
        }

        // Check if the page also has a cononical.
        try {
            $page = Adapter::getInstance()->collection('pages.public')->findOne([
                'definition.route.' . $params['locale'] => utf8_encode($path)
            ]);
        } catch (\Exception $e) { }

        if ($page) {
            if (property_exists($page->definition, 'cononical') && $page->definition->cononical->{$params['locale']}) {
                $path = $page->definition->cononical->{$params['locale']};
            }
        }

        // Add domain and protocol
        return $this->buildRoute($uri, $params['locale'], $path, isset($params['scope']) && $params['scope']);
    }

    private function _getPath($params = [])
    {
        $fallbackLocale = $this->container['fallbackScope']['locale'];
        $currentLocale = $this->container['scope']['locale'] ?? $fallbackLocale;

        $page = false;

        if (isset($params['page'])) {
            $page = Adapter::getInstance()->collection('pages.public')->findOne([
                '$or' => [
                    ['definition.route.' . $params['locale'] => $params['page']],
                    ['definition.route.' . $currentLocale => $params['page']],
                    ['definition.route.' . $fallbackLocale => $params['page']],
                    ['definition.cononical.' . $params['locale'] => $params['page']],
                    ['definition.cononical.' . $currentLocale => $params['page']],
                    ['definition.cononical.' . $fallbackLocale => $params['page']]
                ]
            ]);
        }

        if (isset($params['id'])) {
            // First we will check if we can find the page.
            if ($page) {
                $translator = new Translate($this->container);
                $model = $page->definition->template_config->model->data->model ?? false;
                $adapter = $page->definition->template_config->model->data->adapter ?? false;
                $id = $params['id'];

                $model = $translator->translate($model);
                $adapter = $translator->translate($adapter);
                $id = $translator->translate($id);

                if ($model && $adapter && $id) {
                    // Check if we have a redirect.
                    $redirect = Adapter::getInstance()->collection('routes')->findOne([
                        '$or' => [
                            ['resource.' . $currentLocale => implode('/', [$adapter, $model, $id])],
                            ['resource.' . $fallbackLocale => implode('/', [$adapter, $model, $id])],
                            ['resource' => implode('/', [$adapter, $model, $id])]
                        ],
                        'type' => 'landingpage'
                    ]);

                    if ($redirect) {
                        return $redirect['destination'];
                    }
                }

                if (isset($page->definition->route)) {
                    $route = $page->definition->route->{$params['locale']} ?? $page->definition->route->{$fallbackLocale};

                    if ($route) {
                        return trim($route, '/') . '/' . $params['slug'] . $this->container->settings->get('id_separator') . $params['id'];
                    }
                }
            }

            // We don't have anything else, return the build path
            return $params['page'] . '/' . $params['slug'] . $this->container->settings->get('id_separator') . $params['id'];
        } else if (isset($params['page']) && !isset($params['id'])) {
            if ($page && isset($page->definition->route)) {
                return $page->definition->route;
            }

            return $params['page'];
        } else {
            return '/';
        }
    }

    /**
     * First we will get all the proxy scopes filtered on locale.
     * 
     * All the domains are checked on match and score (based on matched characters) and returned.
     * If no match is found the default domain will be used.
     * 
     * Then the protocol and domain is added to the route, and redirected.
     */
    public function buildRoute(Uri $uri, $requestedLocale, $path, $useCurrentScope = false)
    {
        // If the current domain is the same locale, and has a path match it is ok to use it.
        $fallbackScope = $useCurrentScope ? $this->container->get('scope') : $this->container->get('fallbackScope');
        $scopesGroups = Scopes::getGroups();
        // Shift off the first (default) group.
        array_shift($scopesGroups);
        $proxyScopes = Scopes::parse($scopesGroups);

        $scopes = array_filter($proxyScopes, function($scope) use($requestedLocale, $path) {
            if($scope['locale'] !== $requestedLocale) {
                return false;
            }

            if(strpos($path, $scope['path']) === false) {
                return false;
            }

            return true;
        });
        $scopes = array_values($scopes);
        $scopes = array_map(function($scope) {
            $scope['score'] = isset($scope['path']) ? strlen($scope['path']) : 0;
            return $scope;
        }, $scopes);

        // Sort them by score, highest score first.
        usort($scopes, function($a, $b) {
            if(!isset($a['score']) || !isset($b['score'])) {
                return 0;
            }

            return $a['score'] - $b['score'];
        });

        if(count($scopes)) {
            // Get the first.
            $scope = $scopes[0];

            return $uri->withScheme($scope['protocol'])
                ->withHost($scope['domain'])
                ->withPath(
                    $this->_buildPath($scope, $path)
                )
                ->withQuery('');
        }

        return $uri->withScheme($fallbackScope['protocol'])
            ->withHost($fallbackScope['domain'])
            ->withPath(
                $this->_buildPath($fallbackScope, $path)
            )
            ->withQuery('');
    }

    private function _buildPath($scope, $path)
    {
        $path = isset($scope['path']) ? ltrim(preg_replace('/' . trim($scope['path'], '/') . '/', '', $path, 1), '/') : $path;
        $path = isset($scope['locale_prefix']) ? implode('/', [$scope['locale_prefix'], $path]) : $path;
        $path = trim($path, '/');
        $path = !empty($path) ? $path : '/';

        return $path;
    }
}
