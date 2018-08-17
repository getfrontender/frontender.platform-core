<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\Controllers\Pages\Revisions;
use Frontender\Core\DB\Adapter;
use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;
use MongoDB\BSON\ObjectId;
use Slim\Http\Request;
use Slim\Http\Response;
use Frontender\Core\Routes\Middleware\TokenCheck;

class Pages extends CoreRoute {
	protected $group = '/api/pages';

	use Authorizable;

	protected function registerCreateRoutes() {
		parent::registerCreateRoutes(); // TODO: Change the autogenerated stub

		$this->app->put( '/revision', function ( Request $request, Response $response ) {
			$json   = $request->getParsedBody();
			$result = \Frontender\Core\Controllers\Pages::add( $json );
			$json['_id'] = $result->getInsertedId()->__toString();

			return $response->withStatus( 200 )
			                ->withJson( $json );
		} );

		$this->app->put( '/{lot_id}/revision', function ( Request $request, Response $response ) {
			$json = $request->getParsedBody();
			$json = Revisions::add( $json );

			return $response->withJson( $json );
		} );

		$this->app->put( '/{lot_id}/trash', function ( Request $request, Response $response ) {
			// Remove the published page, and move all revisions to the trash.
			\Frontender\Core\Controllers\Pages::delete( $request->getAttribute( 'lot_id' ), 'public' );
			Revisions::delete( $request->getAttribute( 'lot_id' ) );

			return $response->withStatus( 200 );
		} );

		$this->app->put( '/{page_id}/public', function ( Request $request, Response $response ) {
			$page = Adapter::getInstance()->toJSON(
				\Frontender\Core\Controllers\Pages::read( $request->getAttribute( 'page_id' ) )
			);

			\Frontender\Core\Controllers\Pages::publish( $page );

			return $response->withStatus( 200 );
		} );

		$this->app->put( '', function ( Request $request, Response $response ) {
			$result = Adapter::getInstance()->collection( 'pages' )->insertOne( $request->getParsedBody(), [
				'upsert'            => true,
				'returnNewDocument' => true
			] );

			$page = Adapter::getInstance()->toJSON(
				Adapter::getInstance()->collection( 'pages' )->findOne( [
					'_id' => new ObjectId( $result->getInsertedId()->__toString() )
				] )
			);

			return $response->withJson( $page );
		} );
	}

	protected function registerReadRoutes() {
		parent::registerReadRoutes();

		$this->app->get( '', function ( Request $request, Response $response ) {
			$json = Adapter::getInstance()->toJSON(
				\Frontender\Core\Controllers\Pages::browse( [
					'collection' => $request->getQueryParam( 'collection' ),
					'lot'        => $request->getQueryParam( 'lot' ),
					'sort'		 => !empty($request->getQueryParam( 'sort' )) ? $request->getQueryParam( 'sort' ) : 'definition.name',
					'direction'	 => !empty($request->getQueryParam( 'direction' )) ? $request->getQueryParam( 'direction' ) : 1,
					'locale'	 => !empty($request->getQueryParam( 'locale' )) ? $request->getQueryParam( 'locale' ) : 'en-GB',
					'filter'     => $request->getQueryParam( 'filter' )
				] )
			);

			return $response->withJson( $json );
		} );

		$this->app->get( '/public', function ( Request $request, Response $response ) {
			$json = Adapter::getInstance()->toJSON(
				\Frontender\Core\Controllers\Pages::browse( [
					'collection' => 'public',
					'sort'		 => !empty($request->getQueryParam( 'sort' )) ? $request->getQueryParam( 'sort' ) : 'definition.name',
					'direction'	 => !empty($request->getQueryParam( 'direction' )) ? $request->getQueryParam( 'direction' ) : 1,
					'locale'	 => !empty($request->getQueryParam( 'locale' )) ? $request->getQueryParam( 'locale' ) : 'en-GB'
				] )
			);

			return $response->withJson( $json );
		} );

		$this->app->get( '/{page_id}', function ( Request $request, Response $response ) {
			$json = Adapter::getInstance()->toJSON(
				\Frontender\Core\Controllers\Pages::read( $request->getAttribute( 'page_id' ) )
			);

			return $response->withJson( $json );
		} );

		$this->app->get( '/{page_id}/preview', function ( Request $request, Response $response) {
			$json = Adapter::getInstance()->toJSON(
				\Frontender\Core\Controllers\Pages::read( $request->getAttribute( 'page_id' ) )
			);
			$json = json_decode(json_encode($json), true);
			$json = \Frontender\Core\Controllers\Pages::sanitize($json['definition']);

			try {
				$page = $this->page;
				$this->language->set($request->getQueryParam('locale'));
				$page->setData( $json );
				$page->setRequest( $request );
				$page->parseData();

				$response->getBody()->write($page->render());
			} catch (\Exception $e) {
				echo $e->getMessage();
				die('Called');
			} catch (\Error $e) {
				echo $e->getMessage();
				echo '<pre>';
				    print_r(array_map(function($trace) {
				    	return $trace['file'] . '::' . $trace['line'];
				    }, $e->getTrace()));
				echo '</pre>';
				die();
			}

			return $response;
		});

		$this->app->get( '/{lot_id}/public', function ( Request $request, Response $response ) {
			$json = Adapter::getInstance()->toJSON(
				Revisions::read( $request->getAttribute( 'lot_id' ), 'public' )
			);

			return $response->withJson( $json );
		} );

		$this->app->get( '/{lot_id}/revision', function ( Request $request, Response $response ) {
			$json = Adapter::getInstance()->toJSON(
				Revisions::read( $request->getAttribute( 'lot_id' ), $request->getQueryParam( 'revision', 'last' ) )
			);

			return $response->withJson( $json );
		} );
		
		$this->app->get( '/{lot_id}/revisions', function ( Request $request, Response $response ) {
			$json = Adapter::getInstance()->toJSON(
				Revisions::read( $request->getAttribute( 'lot_id' ), 'all', -1 )
			);

			return $response->withJson( $json );
		} );

		$this->app->get( '/{lot_id}/trash', function ( Request $request, Response $response ) {
			$json = Adapter::getInstance()->toJSON(
				\Frontender\Core\Controllers\Pages::browse( [
					'collection' => 'trash',
					'lot'        => $request->getAttribute( 'lot_id' ),
					'sort'		 => !empty($request->getQueryParam( 'sort' )) ? $request->getQueryParam( 'sort' ) : 'definition.name',
					'direction'	 => !empty($request->getQueryParam( 'direction' )) ? $request->getQueryParam( 'direction' ) : 1,
					'locale'	 => !empty($request->getQueryParam( 'locale' )) ? $request->getQueryParam( 'locale' ) : 'en-GB'
				] )
			);

			return $response->withJson( $json );
		} );
	}

	protected function registerUpdateRoutes() {
		parent::registerUpdateRoutes();

		$this->app->post( '/{lot_id}', function ( Request $request, Response $response ) {
			$data = \Frontender\Core\Controllers\Pages::update( $request->getAttribute( 'lot_id' ), $request->getParsedBody() );
		} );

		$this->app->post( '/{page_id}/update', function ( Request $request, Response $response) {
			$data = $request->getParsedBody();
			unset($data['_id']);

			Adapter::getInstance()->collection('pages')->updateOne([
				'_id' => new ObjectId($request->getAttribute('page_id'))
			], [
				'$set' => $data
			]);

			return $response->withStatus(200);
		});

		$this->app->post( '/{page_id}/preview', function ( Request $request, Response $response) {
			$body = $request->getParsedBody();
			$json = json_decode($body['data'], true);
			$json = \Frontender\Core\Controllers\Pages::sanitize($json['definition']);

			try {
				$page = $this->page;
				$this->language->set($request->getQueryParam('locale'));
				$page->setData( $json );
				$page->setRequest( $request );
				$page->parseData();

				$response->getBody()->write($page->render());
			} catch (\Exception $e) {
				echo $e->getMessage();
				die('Called');
			} catch (\Error $e) {
				echo $e->getMessage();
				die('Called2');
			}

			return $response;
		});
	}

	public function registerDeleteRoutes() {
		parent::registerDeleteRoutes();

		$this->app->delete( '/{lot_id}/public', function ( Request $request, Response $response ) {
			\Frontender\Core\Controllers\Pages::delete( $request->getAttribute( 'lot_id' ), 'public' );

			return $response->withStatus( 200 );
		} );

		$this->app->delete( '/{lot_id}', function ( Request $request, Response $response ) {
			\Frontender\Core\Controllers\Pages::delete( $request->getAttribute( 'lot_id' ), '' );

			return $response->withStatus( 200 );
		} );
	}

	public function getGroupMiddleware() {
		return [
			new TokenCheck(
				$this->app->getContainer(),
				[
					'exclude' => [
						'/api/pages/{page_id}/preview'
					]
				]
			)
		];
	}
}
