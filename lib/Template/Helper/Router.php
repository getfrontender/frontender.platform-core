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

        $scopes = Scopes::get();
        $uri = $this->container->get('request')->getUri();
        $domain = $uri->getHost();
        $amount = 0;

        if (isset($scopes)) {
            $amount = array_filter($scopes, function ($scope) use ($domain) {
                return $scope['domain'] === $domain;
            });
        }

        if (count($amount) === 1) {
            // If it is a proxy, add the locale anyway.
            // We need the locale here.
            // Use the current domain, without any locale
            return $this->modifyProxyDomain($uri, false, $path);
        } else {
            // Check the scopes for the current locale, and if it has a path.
            $scopes = array_filter($amount, function ($scope) use ($params) {
                return $scope['locale'] === $params['locale'];
            });

            if (count($scopes) === 1) {
                $scope = array_shift($scopes);
                $localePartial = $scope['locale_prefix'] ?? $scope['locale'];
                $localePartial = str_replace('/', '', $localePartial);

                return $this->modifyProxyDomain(
                    $uri,
                    $localePartial,
                    $path
                );
            }

            return $this->modifyProxyDomain(
                $uri,
                $params['locale'],
                $path
            );
        }
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

                $model = $translator->translate($model, [], true);
                $adapter = $translator->translate($adapter, [], true);
                $id = $translator->translate($id, [], true);

                if ($model && $adapter && $id) {
                    // Check if we have a redirect.
                    $redirect = Adapter::getInstance()->collection('routes')->findOne([
                        'resource' => implode('/', [$adapter, $model, $id]),
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

    private function modifyProxyDomain(Uri $uri, $locale, $path)
    {
        // If anything of a proxy domain is in here, we will remove that part and add the domain.
        $scopes = Scopes::get();

        $domains = array_filter($scopes, function ($scope) use ($locale, $path) {
            if (in_array('proxy_path', array_keys($scope))) {
                $tempLocale = str_replace('/', '', $scope['locale_prefix']);
                $tempPath = ltrim($path, '/');
                $tempProxyPath = ltrim($scope['proxy_path'], '/');

                if (!empty($tempPath)) {
                    if (strpos($tempPath, $tempProxyPath) === 0) {
                        // Check if the locale is the same
                        if ($locale) {
                            if ($locale == $tempLocale) {
                                return true;
                            } else {
                                return false;
                            }
                        }
                    }
                }
            }

            return false;
        });

        $uri = $uri->withQuery('');

        if (count($domains)) {
            $domain = array_shift($domains);
            $tempProxyPath = ltrim($domain['proxy_path'], '/');
            $path = ltrim(preg_replace('/' . $tempProxyPath . '/', '', $path, 1), '/');
            $uri = $uri->withHost($domain['domain']);
        } else {
            // We have to reset the host to the "default".
            $domains = array_filter($scopes, function ($scope) use ($locale) {
                // We have to find the same locale, and one that doesn't have a proxy_path.
                $tempLocale = str_replace('/', '', $scope['locale_prefix'] ?? '');
                if ($locale && $tempLocale != $locale) {
                    return false;
                }

                if (in_array('proxy_path', array_keys($scope))) {
                    return false;
                }

                return true;
            });

            if (count($domains)) {
                $domain = array_shift($domains);

                if (isset($domain['domain'])) {
                    $uri = $uri->withHost($domain['domain']);
                }
            }
        }

        if ($locale) {
            $path = array_map(function ($part) {
                return trim($part, '/');
            }, [$locale, $path]);
            $path = array_filter($path);
            return $uri->withPath(implode('/', $path));
        }

        // We now have the locale, and the path
        return $uri->withPath($path ?? '/');
    }
}
