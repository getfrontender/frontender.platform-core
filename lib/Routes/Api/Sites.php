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

		$this->app->get('/settings', function(Request $request, Response $response) {
			$settings = Adapter::getInstance()->collection('settings')->find()->toArray();
			$setting = Adapter::getInstance()->toJSON(array_shift($settings));

			return $response->withJson($setting ?? new \stdClass());
		});
	}

	public function registerUpdateRoutes() {
		parent::registerUpdateRoutes();

		$this->app->post('/settings', function(Request $request, Response $response) {
			$settings = Adapter::getInstance()->collection('settings')->find()->toArray();
			$setting = array_shift($settings);
			$data = $request->getParsedBody();
			unset($data['_id']);

			Adapter::getInstance()->collection('settings')->findOneAndReplace([
				'_id' => $setting->_id
			], $data);

			return $response->withStatus(200);
		});
	}
}