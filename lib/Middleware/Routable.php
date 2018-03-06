<?php
/**
 * @package     Dipity
 * @subpackage  Destinations
 * @copyright   Copyright (C) 2014 Dipity BV (http://www.dipity.eu)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Middleware;

use Prototype\Template\Helper\Router;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class Routable
{
	private $_container;
	private $_router;

	public function __construct($container)
	{
		$this->_container = $container;
		$this->_router = new Router($this->_container);
	}

	public function __invoke(Request $request, Response $response, $next)
	{
		// Ill do it myself, I don't have any data here .....
		$route = $request->getAttribute('route');
		$page = $route->getArgument('page');
		$id = $route->getArgument('id');

		// Check for the id.
		if($page && $id) {
			$routes = json_decode(file_get_contents($this->_container->settings['project']['path'] . '/routes.json'), true);

			// Get the file.
			// We can indeed assume that we have the id.
			$json = json_decode(file_get_contents($this->_container['settings']['project']['path'] . '/pages/published/' . $page . '.json'));
			$model_path = ['template_config', 'model', 'controls', 'name', 'value'];
			$model = array_reduce($model_path, function($json, $index) {
				if(!$json || !$json->{$index}) {
					return false;
				}

				return $json->{$index};
			}, $json);

			$route_path = [$model, $id];
			$route = array_reduce($route_path, function($routes, $index) {
				if(!$routes || !array_key_exists($index, $routes)) {
					return false;
				}

				return $routes[$index];
			}, $routes);

			if($route)
			{
				return $response
					->withStatus(301)
					->withRedirect(str_replace('//', '/', $this->_router->route(['page' => $route['path']])));
			}
		}

		return $next($request, $response);
	}
}