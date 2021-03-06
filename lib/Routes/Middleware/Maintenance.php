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

use Frontender\Core\Template\Helper\Router;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class Maintenance
{
    protected $_container;

    function __construct(\Slim\Container $container)
    {
        $this->_container = $container;
        $this->_router = new Router($this->_container);
    }

    /**
     * The issue is that we don't have a language here anymore, because we can't determine the route before the middleware.
     * If the site is in maintenance, and we can't find the maintenance as the second or first argument in the route, then we must redirect it.
     */
    public function __invoke(Request $request, Response $response, $next)
    {
        $path = $this->_router->route(['page' => 'maintenance']);
        $parts = array_values(array_filter(explode('/', $request->getUri()->getPath())));
        $redir = false;

        if ($this->_container['settings']->get('offline')) {
            if (count($parts) == 1 && $parts[0] !== 'maintenance') {
                $redir = true;
            }

            if (count($parts) > 1 && $parts[1] !== 'maintenance') {
                $redir = true;
            }
        }

        if ($redir) {
            return $response->withStatus(404)
                ->withRedirect($path);
        }

        return $next($request, $response);
    }
}