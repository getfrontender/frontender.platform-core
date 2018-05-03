<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\DB\Adapter;
use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;
use Slim\Http\Request;
use Slim\Http\Response;

class Blueprints extends CoreRoute {
	protected $group = '/api/blueprints';

	use Authorizable;

	protected function registerReadRoutes() {
		parent::registerReadRoutes();

		$this->app->get('', function(Request $request, Response $response) {
			$data = Adapter::getInstance()->collection('blueprints')->find()->toArray();
			$json = Adapter::getInstance()->toJSON($data);

			return $response->withJson($json)
				->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
				->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
		});
	}
}