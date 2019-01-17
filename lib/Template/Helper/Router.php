<?php

/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Template\Helper;

use Frontender\Core\DB\Adapter;
use Slim\Container;
use Slim\Http\Uri;
use Doctrine\Common\Inflector\Inflector;

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
        $fallbackLocale = $this->container->language->get('language');

        if ($this->container->has('scope')) {
            $fallbackLocale = str_replace('/', '', $this->container->scope['locale_prefix']) ?? $this->container->scope['locale'];
        }

        $params['locale'] = $params['locale'] ?? $fallbackLocale;
        $params['slug'] = $params['slug'] ?? '';

	    // If a url is found, we won't even look further
        if (isset($params['url'])) {
            return $params['url'];
        }

        $path = $this->_getPath($params);
        if (is_object($path)) {
            $path = $path->{$params['locale']};
        } else if (is_array($path)) {
            $path = $path[$params['locale']];
        }

	    // Check if the page also has a cononical.
        try {
            $page = Adapter::getInstance()->collection('pages.public')->findOne([
                'definition.route.' . $params['locale'] => utf8_encode($path)
            ]);
        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }

        if ($page) {
            if (property_exists($page->definition, 'cononical') && $page->definition->cononical->{$params['locale']}) {
                $path = $page->definition->cononical->{$params['locale']};
            }
        }

        $settings = Adapter::getInstance()->collection('settings')->find()->toArray();
        $setting = Adapter::getInstance()->toJSON(array_shift($settings), true);
        $uri = $this->container->get('request')->getUri();
        $domain = $uri->getHost();
        $amount = 0;

        if (isset($setting['scopes'])) {
            $amount = array_filter($setting['scopes'], function ($scope) use ($domain) {
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
        if (isset($params['id'])) {
	    	// First we will check if we can find the page.
            $page = Adapter::getInstance()->collection('pages.public')->findOne([
                '$or' => [
                    ['definition.route.' . $params['locale'] => $params['page']],
                    ['definition.cononical.' . $params['locale'] => $params['page']]
                ]
            ]);

            if ($page) {
		    	// TODO: This must change.
			    // Here we also have a slug anyway.
			    // Else the slug is an empty string.

                $model = $page->definition->template_config->model->data->model ?? false;
                $adapter = $page->definition->template_config->model->data->adapter ?? false;
                $id = $params['id'];

                if ($model && $adapter && $id) {
			    	// Check if we have a redirect.
                    $redirect = Adapter::getInstance()->collection('routes.static')->findOne([
                        'source' => implode('/', [$adapter, $model, $id])
                    ]);

                    if ($redirect) {
                        return $redirect['destination'];
                    }
                }
            }

		    // We don't have anything else, return the build path
            return $params['page'] . '/' . $params['slug'] . $this->container->settings->get('id_separator') . $params['id'];
        } else if (array_key_exists('page', $params) && !array_key_exists('id', $params)) {
            return $params['page'];
        } else {
            return '/';
        }
    }

    private function modifyProxyDomain(Uri $uri, $locale, $path)
    {
        // If anything of a proxy domain is in here, we will remove that part and add the domain.
        $settings = Adapter::getInstance()->collection('settings')->find()->toArray();
        $settings = Adapter::getInstance()->toJson($settings, true);
        $setting = array_shift($settings);

        $domains = array_filter($setting['scopes'], function ($scope) use ($locale, $path) {
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
            $path = ltrim(str_replace($tempProxyPath, '', $path), '/');
            $uri = $uri->withHost($domain['domain']);
        } else {
            // We have to reset the host to the "default".
            $domains = array_filter($setting['scopes'], function ($scope) use ($locale) {
                // We have to find the same locale, and one that doesn't have a proxy_path.
                $tempLocale = str_replace('/', '', $scope['locale_prefix']);
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
            return $uri->withPath(implode('/', [$locale, $path]));
        }

        // We now have the locale, and the path
        return $uri->withPath($path ?? '/');
    }
}