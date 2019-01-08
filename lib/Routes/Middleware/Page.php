<?php

namespace Frontender\Core\Routes\Middleware;

use Frontender\Core\DB\Adapter;
use Slim\Http\Request;
use Slim\Http\Response;

class Page
{
    protected $_container;

    function __construct(\Slim\Container $container)
    {
        $this->_container = $container;
    }

    /**
     * This middleware will check the page JSON availability or unavailability.
     *
     * If no page JSON is present, we will check for the redirects.
     */
    public function __invoke(Request $request, Response $response, $next)
    {
		// Exclude api calls
		// Exclude post calls.
        if ($request->getMethod() === 'POST' || strpos($request->getUri()->getPath(), '/api') === 0 || $request->getAttribute('route')->getName() === 'partial') {
            return $next($request, $response);
        }

        $adapter = Adapter::getInstance();
        $settings = $adapter->collection('settings')->find()->toArray();
        $settings = $adapter->toJSON($settings, true);
        $setting = array_shift($settings);
        $fallbackLocale = $setting['scopes'][0];
        $info = $request->getAttribute('routeInfo')[2];

		// Exclude homepage
        if ($request->getAttribute('route')->getName() === 'home') {
            $page = $adapter->collection('pages.public')->findOne([
                '$or' => [
                    ['definition.route.' . $info['locale'] => '/'],
                    ['definition.cononical.' . $info['locale'] => '/'],

                    // Try to find the fallback language.
                    ['definition.route.' . $fallbackLocale['locale'] => '/'],
                    ['definition.cononical.' . $fallbackLocale['locale'] => '/']
                ]
            ]);

            $request = $request->withAttribute('json', $page);
            return $next($request, $response);
        }

        // Get the initial path
        $parts = explode('/', $info['page']);
        $templateName = array_pop($parts);
        $page = false;

        if (count($parts)) {
            while (count($parts) > 0) {
                $page = $this->_getPage(implode('/', array_merge($parts, [$templateName])), $info['locale'], $fallbackLocale['locale']);

                if (!$page) {
                    array_pop($parts);
                } else {
                    break;
                }
            }
        }

        if (!$page && !count($parts) && $templateName) {
            $page = $this->_getPage($templateName, $info['locale'], $fallbackLocale['locale']);
        }

        if (!$page) {
            return $this->_findRedirect($request, $response, $adapter);
        }

        if (property_exists($page->definition, 'cononical') && $page->definition->cononical->{$info['locale']}) {
            $cononical = $page->definition->cononical->{$info['locale']};

			// This only needs to happen if we don't have a cononical in the url.
            if (strpos($request->getUri()->getPath(), $cononical) === false) {
                return $this->_setRedirect($request, $response, $cononical);
            }
        }

        if (isset($info['id'])) {
			// Check if there is a redirect/ if so we will follow that.
            $redirect = $adapter->collection('routes.static')->findOne([
                'source' => implode('/', [$page->definition->template_config->model->data->adapter, $page->definition->template_config->model->data->model, $info['id']])
            ]);

            if ($redirect) {
                $redirect = $redirect->destination->{$info['locale']};
                return $this->_setRedirect($request, $response, $redirect);
            }
        }

        $request = $request->withAttribute('json', $page);
        return $next($request, $response);
    }

    private function _findRedirect(Request $request, Response $response, $adapter)
    {
        $static = $adapter->collection('routes.static')->findOne([
            'source' => $request->getUri()->getPath()
        ]);

        if ($static) {
            $info = $request->getAttribute('routeInfo')[2];
            $destination = $static->destination[$info['locale']] ?? '404';

            return $this->_setRedirect($request, $response, $destination);
        }

        $dynamic = $adapter->collection('routes.dynamic')->find()->toArray();
        foreach ($dynamic as $redirect) {
            if (preg_match($redirect->source, $request->getUri()->getPath()) === 1) {
                return $this->_setRedirect($request, $response, $redirect->destination);
            }
        }

        return $this->_setRedirect($request, $response, '404');
    }

    private function _setRedirect(Request $request, Response $response, $redirect)
    {
        if (preg_match('/http[s]?:\/\//', $redirect) === 1) {
            return $response->withRedirect($redirect, 301);
        }

		// TODO: Add a function here to check the domain and the path of that domain.
        $info = $request->getAttribute('routeInfo')[2];
        $settings = Adapter::getInstance()->collection('settings')->find()->toArray();
        $setting = Adapter::getInstance()->toJSON(array_shift($settings), true);
        $uri = $request->getUri();
        $domain = $uri->getHost();

        $amount = array_filter($setting['scopes'], function ($scope) use ($domain) {
            return $scope['domain'] === $domain;
        });

        if (count($amount) === 1) {
			// Use the current domain, without any locale
            $uri = $uri->withPath($redirect);
        } else {
            // Get the current scope
            $prefix = $this->_container->scope['path'] ?? $this->_container->scope['locale'];
            $uri = $uri->withPath(str_replace('/', '', $prefix) . '/' . $redirect);
        }

        return $response->withRedirect($uri, 301);
    }

    private function _getPage($route, $locale, $fallbackLocale)
    {
        $adapter = Adapter::getInstance();

        return $adapter->collection('pages.public')->findOne([
            '$or' => [
                ['definition.route.' . $locale => $route],
                ['definition.cononical.' . $locale => $route],

                    // Try to find the fallback language.
                ['definition.route.' . $fallbackLocale => $route],
                ['definition.cononical.' . $fallbackLocale => $route]
            ]
        ]);
    }
}