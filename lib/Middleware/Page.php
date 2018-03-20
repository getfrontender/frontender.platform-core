<?php

namespace Frontender\Core\Middleware;

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
	 * If a page is found in the route, check if the file exists, if not,
	 * When no route is found, it means that the page isn't there either so the check is skipped.
	 * throw a NotFoundException so the request will go to the notFoundHandler.
	 *
	 * TODO: This is only fixed in context of Brickson!!!
	 */
	public function __invoke(Request $request, Response $response, $next)
	{
		if($this->_container->has('domain')) {
			// I can Assume the path to be the page.
			// I have to strip the first part.
			$parts = explode('/', $request->getUri()->getPath());
			$parts = array_filter($parts);

			array_shift($parts);

			if(count($parts) <= 0 || empty($parts)) {
				$path = 'home';
			} else {
				if(count($parts) === 2) {
					array_pop($parts);
				}

				$path = implode('/', $parts);
			}

			$page = $this->_getPage($path);

			if(!$page) {
				throw new \Slim\Exception\NotFoundException($request, $response);
			}

			return $next($request, $response);
		}

		$route = $request->getAttribute('route');
		$page = $route->getArgument('page');
		$patterns = array_map(function($group) {
			return $group->getPattern();
		}, $route->getGroups());

		$isAPI = in_array('/api', $patterns);

		if($page && !$isAPI) {
			$page = $this->_getPage($page);

			$route->setArgument('page', $page);

			if(!$page) {
				throw new \Slim\Exception\NotFoundException($request, $response);
			}
		}

		return $next($request, $response);
	}

	private function _getPage($path) {
		$root = $this->_container->settings['project']['path'] . '/pages/published/';
		$parts = explode('/', $path);
		$layout = array_pop($parts);
		$exists = false;

		if(count($parts) === 0) {
			$exists = file_exists($root . implode('/', $parts) . '/' . $layout . '.json');
		} else {
			while ( $exists == false && count( $parts ) >= 0 ) {
				$exists = file_exists( $root . implode( '/', $parts ) . '/' . $layout . '.json' );
				if ( ! $exists ) {
					array_pop( $parts );
				}
			}
		}

		if($exists) {
			return implode('/', $parts) . '/' . $layout;
		}

		return false;
	}
}