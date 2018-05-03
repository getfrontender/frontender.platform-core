<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\DB\Adapter;
use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;
use Slim\Http\Request;
use Slim\Http\Response;

class Users extends CoreRoute {
	protected $group = '/api/users';

	use Authorizable;
	
	protected function registerReadRoutes() {
		parent::registerReadRoutes();

		$this->app->get('', function(Request $request, Response $response) {
			$db = Adapter::getInstance();

			$pages = $db->collection('pages.public')->find()->toArray();

//			echo '<pre>';
//			    print_r($pages);
//			echo '</pre>';
//			die();

			return $response->withJson($pages);
		});
	}
}