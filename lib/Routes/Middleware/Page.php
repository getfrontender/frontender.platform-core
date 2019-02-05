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
        if ($request->getMethod() === 'POST' || strpos($request->getUri()->getPath(), '/api') === 0) {
            return $next($request, $response);
        }

        $adapter = Adapter::getInstance();
        $settings = $adapter->collection('settings')->find()->toArray();
        $settings = $adapter->toJSON($settings, true);
        $settings = array_shift($settings);
        $segments = array_filter(explode('/', $request->getUri()->getPath()));
        $locale = array_shift($segments);

        $fallbackScope = $settings['scopes'][0];
        $fallbackLocale = $fallbackScope['locale'];
        $requestId = false;

		// Exclude homepage
        if (empty($segments)) {
            $page = $adapter->collection('pages.public')->findOne([
                '$or' => [
                    ['definition.route.' . $locale => '/'],
                    ['definition.cononical.' . $locale => '/'],

                    // Try to find the fallback language.
                    ['definition.route.' . $fallbackLocale => '/'],
                    ['definition.cononical.' . $fallbackLocale => '/']
                ]
            ]);

            $request = $request->withAttribute('json', $page);
            return $next($request, $response);
        }

        if (stripos(end($segments), $this->_container->config->id_separator)) {
            array_pop($segments);

            $parts = explode($segments, $this->_container->config->id_separator);
            $requestId = end($parts);
        }

        // Get the initial path
        $templateName = array_pop($segments);
        $page = false;

        if (count($segments)) {
            while (count($segments) > 0) {
                $page = $this->_getPage(implode('/', array_merge($segments, [$templateName])), $locale, $fallbackLocale);

                if (!$segments) {
                    array_pop($segments);
                } else {
                    break;
                }
            }
        }

        if (!$page && !count($segments) && $templateName) {
            $page = $this->_getPage($templateName, $locale, $fallbackLocale);
        }

        if (!$page) {
            return $this->_findRedirect($request, $response, $adapter);
        }

        if (property_exists($page->definition, 'cononical') && $page->definition->cononical->{$locale}) {
            $cononical = $page->definition->cononical->{$locale};

			// This only needs to happen if we don't have a cononical in the url.
            if (strpos($request->getUri()->getPath(), $cononical) === false) {
                return $this->_setRedirect($request, $response, $cononical);
            }
        }

        if ($requestId) {
			// Check if there is a redirect/ if so we will follow that.
            $redirect = $adapter->collection('routes.static')->findOne([
                'source' => implode('/', [$page->definition->template_config->model->data->adapter, $page->definition->template_config->model->data->model, $requestId])
            ]);

            if ($redirect) {
                $redirect = $redirect->destination->{$locale} ?? $redirect->destination->{$fallbackLocale};

                if ($redirect) {
                    return $this->_setRedirect($request, $response, $redirect);
                }
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
            $scope = array_shift($amount);
            $prefix = $scope['locale_prefix'] ?? $scope['locale'];
            $uri = $uri->withPath(str_replace('/', '', $prefix) . '/' . $redirect);
        } else {
            // Get the current scope
            $prefix = $this->_container->scope['locale_prefix'] ?? $this->_container->scope['locale'];
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