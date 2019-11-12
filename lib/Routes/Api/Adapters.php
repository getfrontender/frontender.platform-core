<?php
/*******************************************************
 * @copyright 2017-2019 Dipity B.V., The Netherlands
 * @package Frontender
 * @subpackage Frontender Platform Core
 *
 * Frontender is a web application development platform consisting of a
 * desktop application (Frontender Desktop) and a web application which
 * consists of a client component (Frontender Platform) and a core
 * component (Frontender Platform Core).
 *
 * Frontender Desktop, Frontender Platform and Frontender Platform Core
 * may not be copied and/or distributed without the express
 * permission of Dipity B.V.
 *******************************************************/

namespace Frontender\Core\Routes\Api;

use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;
use Slim\Http\Request;
use Slim\Http\Response;
use Symfony\Component\Finder\Finder;
use Frontender\Core\Routes\Middleware\TokenCheck;
use Frontender\Core\DB\Adapter;
use Frontender\Core\Routes\Middleware\ApiLocale;
use Frontender\Core\Template\Filter\Translate;

class Adapters extends CoreRoute
{
    protected $group = '/api/adapters';

    use Authorizable;

    public function registerReadRoutes()
    {
        parent::registerReadRoutes();

        $container = $config = $this->app->getContainer();
        $modelPath = dirname($config->settings['project']['path']) . '/lib/Model';

        $this->app->get('', function (Request $request, Response $response) use ($modelPath, $container) {
            $finder = new Finder();
            $models = $finder->files()->name('*.json')->in($modelPath);
            $adapters = [];
            $translator = new Translate($container);

            foreach ($models as $file) {
	            // Lets get all the available models.
	            // I have to get the first directory path, this is the adapter name.
	            $adapterName = explode('/', $file->getRelativePath())[0];

	            $adapterList = array_filter($adapters, function($adapter) use ($adapterName) {
		            return $adapter['label'] == $adapterName;
	            });
	            $adapterList = array_values($adapterList);
	            $adapterExists = count($adapterList);
	            $adapterKey = false;

	            if(!$adapterExists) {
		            $adapter = [
			            'label' => $adapterName,
			            'value' => $adapterName,
			            'models' => []
		            ];
	            } else {
	            	$adapterKey = array_search($adapterList[0], $adapters);
		            $adapter = $adapterList[0];
	            }

	            $modelConfig = json_decode($file->getContents(), true);

	            if(isset($modelConfig['has_preview']) && $modelConfig['has_preview']) {
		            $adapter['models'][] = array_merge(
			            $modelConfig,
			            ['value' => str_replace([$adapterName . '/', 'Model.json', '/'], ['', '', '\\'], $file->getRelativePathname())]
		            );
	            }

	            usort($adapter['models'], function($a, $b) use ($translator) {
		            $aLabel = $translator->translate($a['label']);
		            $bLabel = $translator->translate($b['label']);

		            return strnatcmp($aLabel, $bLabel);
	            });

	            if(!$adapterExists) {
		            $adapters[] = $adapter;
	            } else {
	            	// Get the index of the current adapter in the adapters array.
		            if($adapterKey !== false) {
		            	$adapters[$adapterKey] = $adapter;
		            }
	            }
            }

            return $response->withJson($adapters);
        });

        $this->app->get('/models/{adapter}', function (Request $request, Response $response) use ($modelPath) {
            // Get all the models and send them back.
            $finder = new Finder();
            $files = $finder->files()->name('*.php')->in($modelPath . '/' . $request->getAttribute('adapter'));
            $models = [];

            foreach ($files as $file) {
                $path = $file->getPathName();
                $parts = explode('.', $path);
                array_pop($parts);
                $parts[] = 'json';

                $json_definition = implode('.', $parts);
                if (file_exists($json_definition)) {
                    $definition = json_decode(file_get_contents($json_definition), true);
                    $definition['value'] = strtolower(str_replace('Model.php', '', $file->getFilename()));

                    $models[] = $definition;
                }
            }

            return $response->withJson($models);
        });

        $this->app->get('/config/preview', function (Request $request, Response $response) use ($config) {
            $adapter = Adapter::getInstance();
            $settings = $adapter->collection('settings')->find()->toArray();

            if (!count($settings)) {
                return $response->withStatus(404);
            }

            $setting = array_shift($settings);
            if (!isset($setting->preview_settings)) {
                return $response->withStatus(404);
            }

            return $response->withJson($setting->preview_settings);
        });

        $this->app->get('/content/{adapter}/{model:.*}', function (Request $request, Response $response) use ($config) {
            // We have an autoload for it.
            // We also have to support namespaced models.
            $modelParts = explode('/', $request->getAttribute('model'));
            $modelParts = array_map(function ($item) {
                return ucfirst(strtolower($item));
            }, $modelParts);
            $modelName = implode('\\', $modelParts);

            $classname = '\\Frontender\\Platform\\Model\\' . $request->getAttribute('adapter') . '\\' . $modelName . 'Model';
            $model = new $classname($config);
            $model->setState($request->getQueryParams());
            $items = $model->fetch();
            $path = new \ReflectionClass($classname);
            $path = str_replace('.php', '.json', $path->getFileName());
            $mapping = json_decode(file_get_contents($path), true);
            $data = [
                'items' => [],
                'states' => $model->getState()->getValues(),
                'total' => $model->getTotal(),
                'config' => $mapping
            ];

            if ($items) {
                $data['items'] = array_map(function ($entry) use ($mapping) {
                    $newItem = [];

                    foreach ($mapping['map'] as $key => $map) {
                        $newItem[$key] = array_reduce(explode('.', $map), function ($object, $key) {
                            if (!$object || !isset($object[$key])) {
                                return false;
                            }

                            return $object[$key];
                        }, $entry);
                    }

                    return $newItem;
                }, $items);
            }

            return $response->withJson($data);
        });
    }

    public function getGroupMiddleware()
    {
        return [
            new TokenCheck(
                $this->app->getContainer()
            ),
            new ApiLocale($this->app->getContainer())
        ];
    }
}
