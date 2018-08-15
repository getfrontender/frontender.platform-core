<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;
use Slim\Http\Request;
use Slim\Http\Response;
use Symfony\Component\Finder\Finder;
use Frontender\Core\Routes\Middleware\TokenCheck;

class Adapters extends CoreRoute {
	protected $group = '/api/adapters';

	use Authorizable;

	public function registerReadRoutes() {
		parent::registerReadRoutes();

		$config = $this->app->getContainer();
		$modelPath = dirname($config->settings['project']['path']) . '/lib/Model';

		$this->app->get('', function (Request $request, Response $response) use ($modelPath) {
			$finder = new Finder();
			$files = $finder->directories()->exclude('State')->in($modelPath)->depth(0);
			$adapters = [];

			foreach($files as $file) {
				// Lets get all the available models.
				$modelFinder = new Finder();
				$models = $modelFinder->files()->name('*.json')->in($file->getPathName());
				$adapter = [
					'name' => $file->getFileName(),
					'models' => []
				];

				foreach($models as $model) {
					$definition = json_decode($model->getContents(), true);
					$definition['value'] = strtolower(str_replace('Model.json', '', $model->getFilename()));

					$adapter['models'][] = $definition;
				}

				$adapters[] = $adapter;
			}

			return $response->withJson($adapters);
		});
		
		$this->app->get('/models/{adapter}', function(Request $request, Response $response) use ($modelPath) {
			// Get all the models and send them back.
			$finder = new Finder();
			$files = $finder->files()->name('*.php')->in($modelPath . '/' . $request->getAttribute('adapter'));
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

		$this->app->get('/content/{adapter}/{model}', function (Request $request, Response $response) use ($config) {
			// We have an autoload for it.
			$classname = '\\Prototype\\Model\\' . $request->getAttribute('adapter') . '\\' . ucfirst(strtolower($request->getAttribute('model'))) . 'Model';
			$model = new $classname($config);
			$model->setState($request->getQueryParams());
			$items = $model->fetch();
			$data = [];
			$path = new \ReflectionClass($classname);
			$path = str_replace('.php', '.json', $path->getFileName());
			$mapping = json_decode(file_get_contents($path), true);

			if($items) {
				$data['items'] = array_map(function($entry) use ($mapping) {
					$newItem = [];

					foreach($mapping['map'] as $key => $map) {
						$newItem[$key] = array_reduce(explode('.', $map), function($object, $key) {
							if(!$object || !isset($key, $object)) {
								return false;
							}

							return $object[$key];
						}, $entry);
					}

					return $newItem;
				}, $items);
				$data['states'] = $model->getState()->getValues();
				$data['total'] = $model->getTotal();
			}

			return $response->withJson($data);
		});
	}

	public function getGroupMiddleware() {
		return [
			new TokenCheck(
				$this->app->getContainer()
			)
		];
	}
}