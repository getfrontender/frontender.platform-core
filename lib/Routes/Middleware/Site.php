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

namespace Frontender\Core\Routes\Middleware;

use FastRoute\Dispatcher;
use Frontender\Core\DB\Adapter;
use Slim\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Frontender\Core\Routes\Exceptions\NotImplemented;
use Frontender\Core\Utils\Scopes;

/**
 * The Sitable middleware is the heart of the multi-site functionality.
 *
 * What will this do:
 * 1. It will check if the site is present as a main container.
 *    1a. If the site isn't the main container than check if it is a language alias
 * 2. If the site is found, do a internal redirect.
 * 3. If the site isn't found as a main container or language alias, return a 404 error.
 */
class Site
{
    protected $_container;

    function __construct(\Slim\Container $container)
    {
        $this->_container = $container;
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        $router = $this->_container->get('router');
        $scopes = Scopes::get();

        if (!$scopes) {
            throw new NotFoundException($request, $response);
        }

        /**
         * Define the kind of scopes that we have.
         * The first group by default is the default group, the rest are for the proxy scopes.
         */
        $scopesGroups = Scopes::getGroups();
        $defaultScopes = Scopes::parse([array_shift($scopesGroups)]);
        $proxyScopes = count($scopesGroups) ? Scopes::parse($scopesGroups) : [];

        $host = $request->getUri()->getHost();
        $path = $request->getUri()->getPath();
        $segments = array_filter(explode('/', $path));
        $segments = array_values($segments);

        $hosts = array_filter($scopes, function ($scope) use ($host) {
            return $scope['domain'] == $host;
        });
        $hosts = array_map(function ($host) {
            if (isset($host['locale_prefix'])) {
                $host['locale_prefix'] = trim($host['locale_prefix'], '/');
            }

            return $host;
        }, $hosts);
        $hosts = array_values($hosts);

        if (strpos($request->getUri()->getPath(), '/api') === 0) {
            $this->_container['fallbackScope'] = $scopes[0];
            return $next($request, $response);
        }

        if (empty($hosts)) {
            // If a host is requested, that is not handled by the platform, we will return
            // a 501 (Not Implemented) response.
            throw new NotImplemented($request, $response);
        }

        // Only when we know that the host is supported, we will get the locale_prefixes.
        $localePrefixes = [];
        foreach ($hosts as $index => $host) {
            if (!isset($host['locale_prefix'])) {
                continue;
            }

            $localePrefixes[$host['locale_prefix']] = $index;
        }
        $host = $hosts[0];

        // Check if path is set, if not, redirect to default locale homepage.
        if (empty($segments) && !empty($localePrefixes)) {
            return $response->withRedirect(
                $request->getUri()->withPath($host['locale_prefix'])
            );
        }

        if (current($segments) && isset($localePrefixes[current($segments)])) {
            $index = $localePrefixes[current($segments)];
            $host = $hosts[$index];

            $localeSegment = array_shift($segments);
        } else {
            $localeSegment = $host['locale'];
        }

        $locale = $host['locale'];
        $path = implode('/', $segments);
        $proxies = array_filter($proxyScopes, function ($scope) use ($path, $locale, $host) {
            if (!isset($scope['path'])) {
                return false;
            }

            if ($scope['locale'] !== $locale) {
                return false;
            }

            $pathPrefix = isset($host['path']) && !empty($host['path']) ? $host['path'] : '';
            $regexString = trim($pathPrefix . $scope['path'], '/');
            $regexString = str_replace('/', '\/', $regexString);
            $regexString = '/^' . $regexString . '$|^' . $regexString . '\/.*$/i';

            return preg_match($regexString, $path, $matches) === 1;
        });

        $proxies = array_values(array_filter($proxies));

        if (!empty($proxies)) {
            $proxy = $proxies[0];
            $path = preg_replace('/^' . trim($proxy['path'], '/') . '/i', '', $path, 1);
            $path = [$proxy['locale_prefix'], $path];
            $path = array_map(function ($segment) {
                return trim($segment, '/');
            }, $path);

            return $response->withRedirect(
                $request->getUri()
                    ->withHost($proxy['domain'])
                    ->withPath(implode('/', $path)),
                302
            );
        }

        if (isset($host['path'])) {
            array_unshift($segments, trim($host['path'], '/'));
        }

        if (isset($localeSegment)) {
            array_unshift($segments, $localeSegment);
        }

        $uri = $request->getUri();
        $request = $request->withUri(
            $uri->withPath(implode('/', $segments))
        );

        // Before we will continue through the system, we will set the current scope.
        // This is required elsewere.
        $this->_container['scope'] = $host;
        $this->_container['fallbackScope'] = $defaultScopes[0];
        $this->_container->language->set($locale);

        return $next($request, $response);
    }
}
