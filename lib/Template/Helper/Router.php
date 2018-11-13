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
            $fallbackLocale = str_replace('/', '', $this->container->scope['path']) ?? $this->container->scope['locale'];
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
            die('Called');
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
		    // Use the current domain, without any locale
            return $uri->withPath($path)
                ->withQuery('');
        } else {
            // Check the scopes for the current locale, and if it has a path.
            $scopes = array_filter($amount, function ($scope) use ($params) {
                return $scope['locale'] === $params['locale'];
            });

            if (count($scopes) === 1) {
                $scope = array_shift($scopes);
                $localePartial = $scope['path'] ?? $scope['locale'];
                $localePartial = str_replace('/', '', $localePartial);

                return $uri->withPath($localePartial . '/' . $path)
                    ->withQuery('');
            }

            return $uri->withPath($params['locale'] . '/' . $path)
                ->withQuery('');
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

                $model = $page->definition->template_config->model->name ?? false;
                $adapter = $page->definition->template_config->model->adapter ?? false;
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
}