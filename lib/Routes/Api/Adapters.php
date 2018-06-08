<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;
use Slim\Http\Request;
use Slim\Http\Response;
use Symfony\Component\Finder\Finder;

class Adapters extends CoreRoute {
	protected $group = '/api/adapters';

	use Authorizable;

	public function registerReadRoutes() {
		parent::registerReadRoutes();

		$config = $this->app->getContainer();
		$modelPath = dirname($config->settings['project']['path']) . '/lib/Model';
		
		$this->app->get('/models[/{adapter}]', function(Request $request, Response $response) use ($modelPath) {
			// Get all the models and send them back.
			$finder = new Finder();
			$files = $finder->files()->name('*.php')->in($modelPath);
			$models = [];

			foreach($files as $file) {
				$path = $file->getPathName();
				$parts = explode('.', $path);
				array_pop($parts);
				$parts[] = 'json';

				$json_definition = implode('.', $parts);
				if(file_exists($json_definition)) {
					$definition = json_decode(file_get_contents($json_definition), true);
					$definition['value'] = strtolower(str_replace('Model.php', '', $file->getFilename()));

					$models[] = $definition;
				}
			}

			return $response->withJson($models);
		});

		$this->app->get('/content/{model}[/{adapter}]', function (Request $request, Response $response) use ($config) {
			// We have an autoload for it.
			$classname = '\\Prototype\\Model\\' . ucfirst(strtolower($request->getAttribute('model'))) . 'Model';
			$model = new $classname($config);
			$data = $model->fetch();

			if($data) {
				return $response->withJson(array_map(function($entry) {
					return $entry->toArray();
				}, $data));
			}

			return $response->withJson($data);
		});
	}
}