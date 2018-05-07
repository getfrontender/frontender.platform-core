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
			$json = Adapter::getInstance()->toJSON(
				\Frontender\Core\Controllers\Blueprints::browse()
			);

			return $response->withJson($json);
		});
	}
}