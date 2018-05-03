<?php

namespace Frontender\Core\Routes\Middleware;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
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
        $route = $request->getAttribute('route');
        $isAPI = false;

        if($route) {
            $patterns = array_map(function($group) {
                return $group->getPattern();
            }, $route->getGroups());

            $isAPI = in_array('/api', $patterns);
        }

        // If the route is defined we mostly have an API, however we still have to check.
        if(!$isAPI) {
            $finder = new Finder();
            $uri = $request->getUri();
            $host = $uri->getHost();
            $sites = $finder->ignoreUnreadableDirs()->in(ROOT_PATH . '/project/sites')->files()->name('*.json');
            $language = null;
            $isFound = false;
            $domain = false;

            foreach ($sites as $site) {
                // First check if there are multiple languages.
                // Else use that one language.
                $site = json_decode($site->getContents(), true);

                if (count($site['languages']) > 1) {
                    foreach ($site['languages'] as $lang) {
                        if (isset($lang['domain']) && $lang['domain'] === $host) {
                            $domain = $lang['domain'];
                            $language = substr($lang['iso'], 0, 2);
                            $isFound = true;
                        }
                    }
                } else {
                    $domain = $site['languages'][0]['domain'];
                    $language = substr($site['languages'][0]['iso'], 0, 2);
                    $isFound = true;
                }

                // Check if the domain is found, else continue.
                if ($site['domain'] === $host) {
                    $domain = $host;
                    $isFound = true;
                }

                if ($isFound) {
                    break;
                }
            }

            if (!$isFound) {
                throw new \Slim\Exception\NotFoundException($request, $response);
            }

            // If the language is found, prepend it to the path, this must be prepended, else we might get conflicts.
            // Also I have to check if the language isn't already set.
            $environment = $this->_container->environment;

            if($route && strlen($route->getArgument('locale')) === 2) {
	            $this->_container['language']->set($route->getArgument('locale'));
            }

            if ($language) {
	            $uri = $request->getUri();
	            $path = $uri->getPath();

	            // prepend the path.
	            $uri = $uri->withPath('/' . $language . $path);
	            $this->_container['domain'] = $domain;

	            $uri = \Slim\Http\Uri::createFromString($uri);
	            $request = $request->withUri($uri);
            }
        }

        return $next($request, $response);
    }
}
