<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\DB\Adapter;
use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;
use MongoDB\BSON\ObjectId;
use Slim\Http\Request;
use Slim\Http\Response;

class Pages extends CoreRoute {
	protected $group = '/api/pages';

	use Authorizable;

	protected function registerReadRoutes() {
		parent::registerReadRoutes();

		$this->app->get('', function(Request $request, Response $response) {
			$json = Adapter::getInstance()->toJSON(
				\Frontender\Core\Controllers\Pages::browse()
			);

			return $response->withJson($json);
		});
	}

	protected function registerUpdateRoutes() {
		parent::registerUpdateRoutes();

		$this->app->post('/{lot_id}', function(Request $request, Response $response) {
			$data = \Frontender\Core\Controllers\Pages::update($request->getAttribute('lot_id'), $request->getParsedBody());

			var_dump($data);
			echo '<pre>';
			    print_r($data);
			echo '</pre>';
			die();
		});
	}

	protected function registerDeleteRoutes() {
		parent::registerDeleteRoutes();

		$this->app->delete('/{lot_id}/trash', function(Request $request, Response $response) {
			$published = Adapter::getInstance()->collection('pages.public')->findOne([
				'revision.lot' => $request->getAttribute('lot_id')
			]);
			$revisions = Adapter::getInstance()->collection('pages')->find([
				'revision.lot' => $request->getAttribute('lot_id')
			]);
		});
	}
}