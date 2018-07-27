<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\DB\Adapter;
use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;
use Slim\Http\Request;
use Slim\Http\Response;

class Sites extends CoreRoute {
	protected $group = '/api/sites';

	public function registerReadRoutes() {
		parent::registerReadRoutes();

		$this->app->get( '/settings', function ( Request $request, Response $response ) {
			$settings = Adapter::getInstance()->collection( 'settings' )->find()->toArray();
			$setting  = Adapter::getInstance()->toJSON( array_shift( $settings ) );

			return $response->withJson( $setting ?? new \stdClass() );
		} );

		$this->app->get( '/reset_settings', function ( Request $request, Response $response ) {
			$client = new \GuzzleHttp\Client();
			$res = $client->get('http://manager.getfrontender.com/api/sites/?id=' . $request->getQueryParam('site_id'));

			$content = json_decode($res->getBody()->getContents(), true);
			$content['scopes'] = json_decode($content['scopes']);

			Adapter::getInstance()->collection('settings')->drop();
			Adapter::getInstance()->collection('settings')->insertOne($content);

			return $response->withStatus(200);
		} );
	}

	public function registerUpdateRoutes() {
		parent::registerUpdateRoutes();

		$this->app->post( '/settings', function ( Request $request, Response $response ) {
			$settings = Adapter::getInstance()->collection( 'settings' )->find()->toArray();
			$setting  = array_shift( $settings );
			$data     = $request->getParsedBody();
			unset( $data['_id'] );

			Adapter::getInstance()->collection( 'settings' )->findOneAndReplace( [
				'_id' => $setting->_id
			], $data );

			return $response->withStatus( 200 );
		} );
	}
}