<?php

namespace Frontender\Core\Routes\Middleware;

use FastRoute\Dispatcher;
use Frontender\Core\DB\Adapter;
use Slim\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * The Sitable middleware is the heart of the multi-site functionality.
 *
 * What will this do:
 * 1. It will check if the site is present as a main container.
 *    1a. If the site isn't the main container than check if it is a language alias
 * 2. If the site is found, do a internal redirect.
 * 3. If the site isn't found as a main container or language alias, return a 404 error.
 */
class Sitable
{
    protected $_container;

    function __construct(\Slim\Container $container)
    {
        $this->_container = $container;
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        if (strpos($request->getUri()->getPath(), '/api') === 0) {
            return $next($request, $response);
        }

        $router = $this->_container->get('router');
        $settings = Adapter::getInstance()->collection('settings')->find()->toArray();
        $setting = array_shift($settings);

        if (!$setting) {
            throw new NotFoundException($request, $response);
        }

        $setting = Adapter::getInstance()->toJSON($setting, true);

        $request = $this->checkProxyDomains($request, $response, $setting);

        if ($request instanceof Response) {
            return $request;
        }

        $domains = array_column($setting['scopes'], 'domain');
        $routeInfo = $request->getAttribute('routeInfo');

        if (!in_array($request->getUri()->getHost(), $domains)) {
            throw new NotFoundException($request, $response);
        }

        // TODO: Add some check for the path.

        $uri = $request->getUri();
        $domain = $uri->getHost();
        $path = $uri->getPath();
	    // First is the default.
        $index = array_search($domain, $domains);
        $scope = $setting['scopes'][$index];
        $amount = array_filter($setting['scopes'], function ($scope) use ($domain, $path, $routeInfo) {
            if ($scope['domain'] === $domain) {
                if (isset($scope['path'])) {
                    if (strpos($path, $scope['path']) === 0) {
                        return true;
                    }
                }

                if (isset($scope['locale']) && isset($routeInfo[2]) && isset($routeInfo[2]['locale'])) {
                    if ($scope['locale'] === $routeInfo[2]['locale']) {
                        return true;
                    }
                }
            }

            return false;
        });
        $currentScope = array_slice($amount, 0, 1);
        $currentScope = array_shift($currentScope);

        // Set the current scope.
        $this->_container->scope = $currentScope;

        /**
         * $scope: This is the fallback/ default scope.
         * $amount: The scopes in which the current domain is found.
         */
        if (count($amount) > 1 || !count($amount)) {
            if (isset($routeInfo[2]['locale'])) {
                return $next($request, $response);
            } else {
                $prefix = $scope['path'] ?? $scope['locale'];

                return $response->withRedirect(
                    $uri->withPath(
                        str_replace('//', '/', $prefix . $uri->getPath())
                    )
                );
            }
        }

        if ($currentScope['locale'] === $routeInfo[2]['locale'] && isset($currentScope['path'])) {
            $path = $uri->getPath();
            $path = str_replace('/' . $currentScope['locale'] . '/', $currentScope['path'], $path);

            $uri = $uri->withPath($path);

            return $response->withRedirect($uri);
        }

        $prefix = $scope['path'] ?? $scope['locale'];
        $uri = $request->getUri();

	    // The following path comes directly from the App code from Slim framework.
	    // This does what we need it to do and this will work for us.
	    // Way better than internal redirects.
        $request = $request->withUri($uri);
        $routeInfo = $router->dispatch($request);

        /**
         * I will check if we have a locale,
         * if we have the locale, then we will check the current scope if it is the path or the locale itself,
         * if it is the locale we won't touch it.
         * 
         * If it is the path, we will change it to the locale so the language is set correctly.
         */

        if (isset($routeInfo[2]) && isset($routeInfo[2]['locale'])) {
            // We will check if our current scope has a path, else we will leave it.
            if (isset($currentScope['path'])) {
                $basePath = str_replace('/', '', $currentScope['path']);

                if ($routeInfo[2]['locale'] === $basePath) {
                    $routeInfo[2]['locale'] = $currentScope['locale'];
                }
            }
        }

        if ($routeInfo[0] !== Dispatcher::FOUND) {
            throw new NotFoundException($request, $response);
        }

        $routeArguments = [];
        foreach ($routeInfo[2] as $k => $v) {
            $routeArguments[$k] = urldecode($v);
        }

        $route = $router->lookupRoute($routeInfo[1]);
        $route->prepare($request, $routeArguments);

	    // add route to the request's attributes in case a middleware or handler needs access to the route
        $routeInfo['request'] = [$request->getMethod(), (string)$request->getUri()];
        $request = $request->withAttribute('route', $route)
            ->withAttribute('routeInfo', $routeInfo);

        return $next($request, $response);
    }

    /**
     * This method will check the addon domains, if it isn't found, then we will 
     */
    public function checkProxyDomains(Request $request, Response $response, $settings)
    {
        if (!isset($settings['proxies']) || !count($settings['proxies'])) {
            // There are no addon domains, so we will do nothing.
            return $request;
        }

        $host = $request->getUri()->getHost();
        $addonDomains = array_keys($settings['proxies']);

        if (!in_array($host, $addonDomains)) {
            // Check if the path is one of the subdomains, if so we will return a redirect.
            $info = $request->getAttribute('routeInfo');
            $uriPath = $request->getUri()->getPath();
            $locale = '';

            if (isset($info[2]) && isset($info[2]['locale'])) {
                $locale = '/' . $info[2]['locale'];
                $uriPath = str_replace($locale, '', $uriPath);
            }

            $paths = array_filter($settings['proxies'], function ($path) use ($uriPath) {
                return strpos($uriPath, $path) === 0;
            });

            if (count($paths)) {
                $uri = $request->getUri();
                $domain = key($paths);
                $path = $paths[$domain];

                return $response->withRedirect(
                    $uri->withHost($domain)
                        ->withPath($locale . str_replace($path, '', $uriPath))
                );
            }

            return $request;
        }

        $domain = array_shift($settings['scopes']);
        $path = $settings['proxies'][$host];

        $uri = $request->getUri();
        $info = $request->getAttribute('routeInfo');
        $uriPath = $uri->getPath();
        $locale = '';

        if (isset($info[2]) && isset($info[2]['locale'])) {
            $locale = '/' . $info[2]['locale'];
            $uriPath = str_replace($locale, '', $uriPath);
        }

        $newPath = implode('', [$locale, $path, $uriPath]);

        // Set the original domain name here, this is done because it will check for the languages later on.
        $request = $request->withUri(
            $uri->withPath($newPath)
                ->withHost($domain['domain'])
        );
        $router = $this->_container->get('router');
        $routeInfo = $router->dispatch($request);

        $routeArguments = [];
        foreach ($routeInfo[2] as $k => $v) {
            $routeArguments[$k] = urldecode($v);
        }

        $route = $router->lookupRoute($routeInfo[1]);
        $route->prepare($request, $routeArguments);

	    // add route to the request's attributes in case a middleware or handler needs access to the route
        $routeInfo['request'] = [$request->getMethod(), (string)$request->getUri()];
        return $request->withAttribute('route', $route)
            ->withAttribute('routeInfo', $routeInfo);
    }
}
