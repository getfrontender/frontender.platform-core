<?php

namespace Frontender\Core\Routes\Middleware;

use Frontender\Core\DB\Adapter;
use Slim\Http\Request;
use Slim\Http\Response;

class Page {
	protected $_container;

	function __construct( \Slim\Container $container ) {
		$this->_container = $container;
	}

	/**
	 * This middleware will check the page JSON availability or unavailability.
	 *
	 * If no page JSON is present, we will check for the redirects.
	 */
	public function __invoke( Request $request, Response $response, $next ) {
		// Exclude api calls
		if ( strpos( $request->getUri()->getPath(), '/api' ) === 0 ) {
			return $next( $request, $response );
		}

		// Exclude homepage
		if ( $request->getAttribute( 'route' )->getName() === 'home' ) {
			return $next( $request, $response );
		}

		$adapter = Adapter::getInstance();
		$info    = $request->getAttribute( 'routeInfo' )[2];

		$page = $adapter->collection( 'pages.public' )->findOne( [
			'definition.route.' . $info['locale'] => $info['page']
		] );

		if ( !$page ) {
			return $this->_findRedirect( $request, $response, $adapter );
		}

		if($page->definition->cononical->{$info['locale']}) {
			return $this->_setRedirect($request, $response, $page->definition->cononical->{$info['locale']});
		}

		// Check if there is a redirect/ if so we will follow that.
		$redirect = $adapter->collection( 'routes.static' )->findOne( [
			'source' => $page->definition->template_config->model->name . '/' . $info['id']
		] );
		$redirect = $redirect->destination->{$info['locale']};

		if ( $redirect ) {
			return $this->_setRedirect($request, $response, $info['locale'] . '/' . $redirect);
		}

		return $next( $request, $response );
	}

	private function _findRedirect(Request $request, Response $response, $adapter) {
		$static = $adapter->collection('routes.static')->findOne([
			'source' => $request->getUri()->getPath()
		]);

		if($static) {
			return $this->_setRedirect($request, $response, $static->destination);
		}

		$dynamic = $adapter->collection('routes.dynamic')->find()->toArray();
		foreach($dynamic as $redirect) {
			if(preg_match($redirect->source, $request->getUri()->getPath()) === 1) {
				return $this->_setRedirect($request, $response, $redirect->destination);
			}
		}

		return $this->_setRedirect($request, $response, '/404');
	}

	private function _setRedirect(Request $request, Response $response, $redirect) {
		if ( preg_match( '/http[s]?:\/\//', $redirect ) === 1 ) {
			return $response->withRedirect( $redirect , 301);
		}

		return $response->withRedirect(
			$request->getUri()
			        ->withPath( $redirect ),
			301
		);
	}
}