<?php

namespace Frontender\Core;

use Frontender\Core\Config\Config;
use Frontender\Core\Language\Language;
use Frontender\Core\Page\DefaultPage;
use Frontender\Core\Routes\Middleware\Page;
use Frontender\Core\Translate\Translate;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Slim\Http\Request;
use Slim\Http\Response;
use Doctrine\Common\Inflector\Inflector;

function getFileJson($file, $assoc = false) {
	return json_decode(file_get_contents($file), $assoc);
}

class App {
	private $appInstance = null;
	private $configInstance = null;

	public function getConfig() {
		if($this->configInstance === null) {
			$this->configInstance = new Config();
		}

		return $this->configInstance;
	}

	public function getApp() {
		if($this->appInstance === null) {
			$config = $this->getConfig();
			$this->appInstance = new \Slim\App($config->toArray());
		}

		return $this->appInstance;
	}

	public function getContainer() {
		$app = $this->getApp();
		return $app->getContainer();
	}

	private function _appendDebug() {
		$config = $this->getConfig();
		$container = $this->getContainer();

		if(!$config->debug) {
			$container['notFoundHandler'] = $container['errorHandler'] = function ($container) {
				return function (Request $request, Response $response, $exception = null) use ($container) {
//					$previous = $exception->getPrevious();
//					$error = [
//						'code' => $exception->getCode() ? $exception->getCode() : 404,
//						'message' => $exception->getMessage() ? $exception->getMessage() : 'Page not found'
//					];
//
//					if ($previous) {
//						$error['code'] = $previous->getCode();
//						$error['message'] = $previous->getResponse()->getReasonPhrase();
//					}

					echo '<pre>';
					    print_r($exception->getMessage());
					echo '</pre>';
					die();

					$parts = array_values(array_filter(explode('/', $request->getUri()->getPath())));
					$locale = $parts[0] ?? 'en';
					$page = '404';

					if(($route = $this->_tryRedirectNotFound($request->getUri())) !== false) {
						return $response->withRedirect($route);
					}

					$container->language->set($locale);
					$data = getFileJson($container->settings['project']['path'] . '/pages/published/' . $page . '.json', true);

//					if($data->containers && count($data->containers) > 1) {
//						$data->containers[1]->template_config = $error;
//					}

					$page = $container->page;
					$page->setParameters(['locale' => $locale, 'debug' => $container->settings['debug'], 'query' => $request->getQueryParams()]);
					$page->setData($data);
					$page->setRequest($request);

					return $response->write($page->render());
				};
			};
		}
	}

	private function _appendMiddleware() {
		$app = $this->getApp();
		$container = $this->getContainer();

//		$app->add(new Middleware\Routable($container));
		$app->add(new Page($container));
		$app->add(new Routes\Middleware\Maintenance($container));
//		$app->add(new Routes\Middleware\Sitable($container));

		/**
		 * This will add the cors headers on every request, still needs to be a little more strict though.
		 */
		$app->add(function(Request $req, Response $res, $next) {
			$response = $next($req, $res);

			return $response
				->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
				->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
		});
	}

	private function _appendContainerData() {
		$container = $this->getContainer();

		$container['language'] = function() {
			return new Language();
		};

		$container['page'] = function ($container) {
			return new DefaultPage($container);
		};

		$container['translate'] = function($container) {
			return new Translate($container);
		};
	}

	private function _tryRedirectNotFound($url) {
		// Check if the redirect file can be found.
		$config  = $this->getConfig();
		$project = $config->project;
		$path    = $url->getPath();

		if ( file_exists( $project['path'] . '/redirects.json' ) ) {
			$redirects = json_decode( file_get_contents( $project['path'] . '/redirects.json' ), true );

			if(array_key_exists('static', $redirects)) {
				foreach($redirects['static'] as $source => $destination) {
					if($source === $path) {
						if(preg_match('/^http[s]?/', $destination) == true) {
							return $destination;
						}

						return $url->withPath($destination);
					}
				}
			}

			if(array_key_exists('dynamic', $redirects)) {
				foreach($redirects['dynamic'] as $regex => $replace) {
					if(preg_match($regex, $path) == true) {
						$path = preg_replace($regex, $replace, $path);

						if(preg_match('/^http[s]?/', $path) == true) {
							return $path;
						}

						return $url->withPath($path);
					}
				}
			}
		}

		return false;
	}

	public function init() {
		$this->_appendDebug();
		$this->_appendMiddleware();
		$this->_appendContainerData();

		foreach(glob(__DIR__ . '/Routes/Api/*') as $file) {
			$name = str_replace('.php', '', basename($file));
			$class = 'Frontender\\Core\\Routes\\Api\\' . $name;

			new $class($this);
		}

		/*$app->group('/api', function() {
			$this->get('/containers', function(Request $request, Response $response) {
				$finder = new Finder();
				$finder
					->ignoreUnreadableDirs()
					->files()
					->in($this->settings['project']['path'] . '/blueprints/containers')
					->name('*.json')
					->sortByName();
				$files = [];

				foreach ($finder as $file) {
					try {
						$content = \json_decode($file->getContents(), true);
					} catch(\Exception $e) {}

					if($content)
					{
						$parts = explode('/', $file->getRelativePath());
						$content['blueprint_type'] = array_shift($parts);

						$files[] = $content;
					}
				}

				return $response
					->withJson(['containers' => $files])
					->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
					->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');;
			});

			$this->get('/containers/{id}', function(Request $request, Response $response) {
				$id = $request->getAttribute('id');
				$path = openssl_decrypt(hex2bin($id), 'blowfish', '');
				$fs = new Filesystem();
				if ($fs->exists($this->settings['project']['path'] .  '/blueprints/containers/' . $path)) {
					$content = [
						'id' => $id
					];
					$content = array_merge($content, \getFileJson($this->settings['project']['path'] .  '/blueprints/containers/' . $path), true);
					$response = $response->withJson(['container' => $content]);
				} else {
//            $response->withHeader($name, $value);
				}
				return $response
					->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
					->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');;
			});

			$this->get('/pages', function(Request $request, Response $response) {
//				authenticate('viewer', $request, $response);

				$directories = [
					$this->settings['project']['path'] . '/pages/unpublished',
					$this->settings['project']['path'] . '/pages/published'
				];
				$directories = array_filter( $directories, function ( $dir ) {
					return is_dir( $dir ) && file_exists( $dir );
				} );


				$pages = new Finder();
				$pages->ignoreUnreadableDirs()->files()->in( $directories )->sortByName();

				$files = [];

				foreach ( $pages as $file ) {
					try {
						$content = [
							// Suppress empty IV error for openssl_encrypt.
							'route'     => str_replace( '.json', '', $file->getRelativePathname() ),
							'publish'   => basename( dirname( $file->getPathName() ) ) !== 'unpublished',
							'thumbnail' => file_exists( $this->settings['project']['path'] . '/thumbnails/' . str_replace( '.json', '', $file->getRelativePathname() ) . '.png' )
						];

						$content               = @array_merge( $content, \json_decode( $file->getContents(), true ) ?: [] );
						$content['containers'] = array_key_exists( 'containers', $content ) ? $content['containers'] : [];
					} catch ( \Exception $e ) {
					}

					$files[] = $content;
				}

				return $response
					->withJson( [ 'pages' => $files ] )
					->withHeader( 'Access-Control-Allow-Origin', '*' )
					->withHeader( 'Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization' )
					->withHeader( 'Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS' );
			});

			$this->put('/pages/{id}', function(Request $request, Response $response) {
				$query = $request->getParsedBody();
				$id = $request->getAttribute('id');
				$page = $query['page'];
				$before = $query['before'];

				$basepath = decrypt($id);

				// The new route is set in the ID.
				// However it will also be posted as plain-text.
				$source = 'pages/' . ($before['publish'] ? 'published' : 'unpublished') . '/' . strtolower($before['route']) . '.json';
				$target = 'pages/' . ($page['publish'] ? 'published' : 'unpublished') . '/' . strtolower($page['route']) . '.json';
				$route = $page['route'];

				unset($page['route']);
				unset($page['publish']);
				$data = json_encode($page);

				// If we have an ID in the file, add it to the routes.
				// Get the model and the id.
				$model = array_reduce(['template_config', 'model', 'controls', 'name', 'value'], function($model, $index) {
					if(!$model || !array_key_exists($index, $model)) {
						return false;
					}

					return $model[$index];
				}, json_decode($data, true));
				$id = array_reduce(['template_config', 'model', 'controls', 'id', 'value'], function($model, $index) {
					if(!$model || !array_key_exists($index, $model)) {
						return false;
					}

					return $model[$index];
				}, json_decode($data, true));

				if($model && $id && strpos($id, '{') === false) {
					$routes_path = $this->settings['project']['path'] . '/routes.json';
					$routes = getFileJson($routes_path, true);

					if(!array_key_exists($model, $routes)) {
						$routes[$model] = [];
					}

					$routes[$model][$id] = [
						'path' => str_replace('//', '/', '/' . $route),
						'publish_at' => time(),
						'modified_at' => time()
					];

					file_put_contents($routes_path, json_encode($routes));
				}

				@unlink($this->settings['project']['path'] . '/' . $source);
				@mkdir(dirname($this->settings['project']['path'] . '/' . $target), 0777, true);
				file_put_contents($this->settings['project']['path'] . '/' . $target, $data);

				// If aliasses are found, add the to the aliasses.json file.
				// LIFO (Last In, First Out) style.
				// Remove the aliasses that are present in the before.
				$aliasses = getFileJson($this->settings['project']['path'] . '/aliasses.json', true);
				if(array_key_exists('alias', $before)) {
					foreach($before['alias'] as $language => $alias) {
						unset($aliasses[$alias]);
					}
				}

				if(array_key_exists('alias', $page)) {
					foreach($page['alias'] as $language => $alias) {
						$aliasses[$alias] = $route;
					}
				}
				file_put_contents($this->settings['project']['path'] . '/aliasses.json', json_encode($aliasses));

				$query['page']['id'] = $id;
				return $response
					->withJson([
						'page' => $query['page']
					])
					->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
					->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
			});

			$this->delete('/pages/{id}', function(Request $request, Response $response) {
				$query = $request->getParsedBody();
				$id = $request->getAttribute('id');
				$page = $query['page'];

				$basepath = decrypt($id);
				@unlink($this->settings['project']['path'] . '/pages/' . ($page['publish'] ? 'published' : 'unpublished') . '/' . $basepath);

				$model = array_reduce(['template_config', 'model', 'controls', 'name', 'value'], function($model, $index) {
					if(!$model || !array_key_exists($index, $model)) {
						return false;
					}

					return $model[$index];
				}, $page);
				$id = array_reduce(['template_config', 'model', 'controls', 'id', 'value'], function($model, $index) {
					if(!$model || !array_key_exists($index, $model)) {
						return false;
					}

					return $model[$index];
				}, $page);

				if($model && $id) {
					$routes_path = $this->settings['project']['path'] . '/routes.json';
					$routes = getFileJson($routes_path, true);

					if(array_key_exists($model, $routes) && array_key_exists($id, $routes[$model])) {
						unset($routes[$model][$id]);
					}

					file_put_contents($routes_path, json_encode($routes));
				}

				return $response
					->withJson([
						'page' => $query['page']
					])
					->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
					->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
			});

			$this->options('/pages/{id}', function(Request $request, Response $response) {
				return $response
					->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
					->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
			});

			$this->get('/pages/{id}', function(Request $request, Response $response) {
				$id = $request->getAttribute('id');
				$path = decrypt($id) . '.json';
				$fs = new Filesystem();

				$parts = explode('/', $path);
				array_shift($parts);
				array_shift($parts);
				$route = implode('/', $parts);

				if ($fs->exists($this->settings['project']['path'] . '/' . $path)) {
					$content = getFileJson($this->settings['project']['path'] . '/' . $path, true);
					$content['route'] = str_replace('.json', '', $route);
					$content['publish'] = basename(dirname($path)) !== 'unpublished';

					$response = $response->withJson(['page' => $content]);
				} else {
//            $response->withHeader($name, $value);
				}
				return $response
					->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
					->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');;
			});

			$this->get('/containers/{id}/render', function(Request $request, Response $response) {
				$id = $request->getAttribute('id');

				if(strpos($id, '^')) {
					$parts = explode('^', $id);
				} else {
					$parts = [null, $id];
				}

				$path = openssl_decrypt(hex2bin($parts[1]), 'blowfish', '');
				$data = (object) [
					'template' => 'layouts/global.html.twig',
					'template_config' => [],
					'containers' => [\getFileJson(ROOT_PATH .  '/project/templates/' . $path)]
				];

				$this->language->set('en');

				$page = $this->page;
				$page->setParameters(['locale' => 'en', 'debug' => false]);
				$page->setData($data);
				$page->setRequest($request);
				$response->getBody()->write($page->render());
				return $response;
			});

			$this->get('/blueprints', function(Request $request, Response $response) {
				$finder = new Finder();
				$finder
					->ignoreUnreadableDirs()
					->files()
					->in([$this->settings['project']['path'] . '/blueprints', $this->settings['core']['path'] . '/blueprints'])
					->name('*.json')
					->sortByName();
				$files = [];


				foreach ($finder as $file) {
					try {
						$content = \json_decode($file->getContents(), true);
					} catch(\Exception $e) {}

					if($content)
					{
						$isCore = strpos($file->getPath(), $this->settings['core']['path']) === 0;
						$parts = explode('/', $file->getRelativePath());

						$content['kind'] = array_shift($parts);
						$content['identifier'] = ($isCore ? 'core' : 'project') . '/' . $file->getRelativePath() . '/' . $file->getBasename('.'.$file->getExtension());
						$files[] = $content;
					}
				}

				return $response
					->withJson(['blueprints' => $files])
					->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
					->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
			});

			$this->get('/layouts', function(Request $request, Response $response) {
				$finder = new Finder();
				$finder
					->ignoreUnreadableDirs()
					->files()
					->in($this->settings['template']['path'] . '/layouts')
					->name('*.html.twig')
					->sortByName();
				$files = [];


				foreach ($finder as $file) {
					$files[] = [
						'label' => [
							'en' => $file->getBasename('.html.twig')
						],
						'value' => 'layouts/' . $file->getFilename()
					];
				}

				return $response
					->withJson(['layouts' => $files])
					->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
					->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
			});

			$this->post('/render', function(Request $request, Response $response) {
				$query = $request->getParsedBody();

				$data = (object) [
					'template' => 'layouts/global.html.twig',
					'template_config' => [],
					'containers' => [\json_decode($query['data'])]
				];

				$this->language->set('en');

				$page = $this->page;
				$page->setParameters(['locale' => 'en', 'debug' => false]);
				$page->setData($data);
				$page->setRequest($request);
				$response->getBody()->write($page->render());
				return $response;
			});

			$this->get('/render', function(Request $request, Response $response) {
				$response->getBody()->write('<div style="height: 65px;"><div style="font:.8em/1 arial; color: rgba(38,50,56,.76);text-align:center;padding-top:1em;">Fetching layout</div><style type="text/css">width:100%;@-webkit-keyframes uil-flickr-anim1{0%{left:0}50%{left:100px}100%{left:0}}@-webkit-keyframes uil-flickr-anim1{0%{left:0}50%{left:100px}100%{left:0}}@-moz-keyframes uil-flickr-anim1{0%{left:0}50%{left:100px}100%{left:0}}@-ms-keyframes uil-flickr-anim1{0%{left:0}50%{left:100px}100%{left:0}}@-moz-keyframes uil-flickr-anim1{0%{left:0}50%{left:100px}100%{left:0}}@-webkit-keyframes uil-flickr-anim1{0%{left:0}50%{left:100px}100%{left:0}}@-o-keyframes uil-flickr-anim1{0%{left:0}50%{left:100px}100%{left:0}}@keyframes uil-flickr-anim1{0%{left:0}50%{left:100px}100%{left:0}}width:100%;@-webkit-keyframes uil-flickr-anim2{0%{left:100px;z-index:1}49%{z-index:1}50%{left:0;z-index:10}100%{left:100px;z-index:10}}@-webkit-keyframes uil-flickr-anim2{0%{left:100px;z-index:1}49%{z-index:1}50%{left:0;z-index:10}100%{left:100px;z-index:10}}@-moz-keyframes uil-flickr-anim2{0%{left:100px;z-index:1}49%{z-index:1}50%{left:0;z-index:10}100%{left:100px;z-index:10}}@-ms-keyframes uil-flickr-anim2{0%{left:100px;z-index:1}49%{z-index:1}50%{left:0;z-index:10}100%{left:100px;z-index:10}}@-moz-keyframes uil-flickr-anim2{0%{left:100px;z-index:1}49%{z-index:1}50%{left:0;z-index:10}100%{left:100px;z-index:10}}@-webkit-keyframes uil-flickr-anim2{0%{left:100px;z-index:1}49%{z-index:1}50%{left:0;z-index:10}100%{left:100px;z-index:10}}@-o-keyframes uil-flickr-anim2{0%{left:100px;z-index:1}49%{z-index:1}50%{left:0;z-index:10}100%{left:100px;z-index:10}}@keyframes uil-flickr-anim2{0%{left:100px;z-index:1}49%{z-index:1}50%{left:0;z-index:10}100%{left:100px;z-index:10}}.uil-flickr-css{background:none;position:relative;width:200px;}.uil-flickr-css>div{width:100px;height:100px;border-radius:50px;position:absolute;top:50px}.uil-flickr-css>div:nth-of-type(1){left:0;background:#263238;z-index:5;-ms-animation:uil-flickr-anim1 1s linear infinite;-moz-animation:uil-flickr-anim1 1s linear infinite;-webkit-animation:uil-flickr-anim1 1s linear infinite;-o-animation:uil-flickr-anim1 1s linear infinite;animation:uil-flickr-anim1 1s linear infinite}.uil-flickr-css>div:nth-of-type(2){left:100px;background:#00897b;-ms-animation:uil-flickr-anim2 1s linear infinite;-moz-animation:uil-flickr-anim2 1s linear infinite;-webkit-animation:uil-flickr-anim2 1s linear infinite;-o-animation:uil-flickr-anim2 1s linear infinite;animation:uil-flickr-anim2 1s linear infinite}</style><div class="uil-flickr-css" style="transform: scale(0.2);margin: 0 auto;"><div></div><div></div></div></div>');
				return $response;
			});

			$this->post('/page/render', function(Request $request, Response $response) {
				$query = $request->getParsedBody();

				$data = \json_decode($query['data']);
				$this->language->set($query['language']);

				$page = $this->page;
				$page->setParameters(['locale' => $query['language'], 'debug' => false, 'query' => $query]);
				$page->setData($data);
				$page->setRequest($request);
				$response->getBody()->write($page->render());
				return $response;
			});

			$this->post('/container/render', function(Request $request, Response $response) {
				$query = $request->getParsedBody();

				$data = \json_decode($query['data']);
				$data = (object) [
					'template' => 'layouts/global.html.twig',
					'template_config' => [],
					'containers' => [$data]
				];

				$this->language->set($query['language']);

				$page = $this->page;
				$page->setParameters(['locale' => $query['language'], 'debug' => false, 'query' => $query]);
				$page->setData($data);
				$page->setRequest($request);
				$response->getBody()->write($page->render());
				return $response;
			});

			$this->get('/page/render', function(Request $request, Response $response) {
				$response->getBody()->write('<div><div style="height: 65px;"><div style="font:.8em/1 arial; color: rgba(38,50,56,.76);text-align:center;padding-top:1em;">Fetching layout</div><style type="text/css">width:100%;@-webkit-keyframes uil-flickr-anim1{0%{left:0}50%{left:100px}100%{left:0}}@-webkit-keyframes uil-flickr-anim1{0%{left:0}50%{left:100px}100%{left:0}}@-moz-keyframes uil-flickr-anim1{0%{left:0}50%{left:100px}100%{left:0}}@-ms-keyframes uil-flickr-anim1{0%{left:0}50%{left:100px}100%{left:0}}@-moz-keyframes uil-flickr-anim1{0%{left:0}50%{left:100px}100%{left:0}}@-webkit-keyframes uil-flickr-anim1{0%{left:0}50%{left:100px}100%{left:0}}@-o-keyframes uil-flickr-anim1{0%{left:0}50%{left:100px}100%{left:0}}@keyframes uil-flickr-anim1{0%{left:0}50%{left:100px}100%{left:0}}width:100%;@-webkit-keyframes uil-flickr-anim2{0%{left:100px;z-index:1}49%{z-index:1}50%{left:0;z-index:10}100%{left:100px;z-index:10}}@-webkit-keyframes uil-flickr-anim2{0%{left:100px;z-index:1}49%{z-index:1}50%{left:0;z-index:10}100%{left:100px;z-index:10}}@-moz-keyframes uil-flickr-anim2{0%{left:100px;z-index:1}49%{z-index:1}50%{left:0;z-index:10}100%{left:100px;z-index:10}}@-ms-keyframes uil-flickr-anim2{0%{left:100px;z-index:1}49%{z-index:1}50%{left:0;z-index:10}100%{left:100px;z-index:10}}@-moz-keyframes uil-flickr-anim2{0%{left:100px;z-index:1}49%{z-index:1}50%{left:0;z-index:10}100%{left:100px;z-index:10}}@-webkit-keyframes uil-flickr-anim2{0%{left:100px;z-index:1}49%{z-index:1}50%{left:0;z-index:10}100%{left:100px;z-index:10}}@-o-keyframes uil-flickr-anim2{0%{left:100px;z-index:1}49%{z-index:1}50%{left:0;z-index:10}100%{left:100px;z-index:10}}@keyframes uil-flickr-anim2{0%{left:100px;z-index:1}49%{z-index:1}50%{left:0;z-index:10}100%{left:100px;z-index:10}}.uil-flickr-css{background:none;position:relative;width:200px;}.uil-flickr-css>div{width:100px;height:100px;border-radius:50px;position:absolute;top:50px}.uil-flickr-css>div:nth-of-type(1){left:0;background:#263238;z-index:5;-ms-animation:uil-flickr-anim1 1s linear infinite;-moz-animation:uil-flickr-anim1 1s linear infinite;-webkit-animation:uil-flickr-anim1 1s linear infinite;-o-animation:uil-flickr-anim1 1s linear infinite;animation:uil-flickr-anim1 1s linear infinite}.uil-flickr-css>div:nth-of-type(2){left:100px;background:#00897b;-ms-animation:uil-flickr-anim2 1s linear infinite;-moz-animation:uil-flickr-anim2 1s linear infinite;-webkit-animation:uil-flickr-anim2 1s linear infinite;-o-animation:uil-flickr-anim2 1s linear infinite;animation:uil-flickr-anim2 1s linear infinite}</style><div class="uil-flickr-css" style="transform: scale(0.2);margin: 0 auto;"><div></div><div></div></div></div></div>');
				return $response;
			});

			/******** Sites ********/
			/*$this->get('/site/{domain}', function(Request $request, Response $response) {
				// Loop through all the files and check the content.
				$finder = new Finder();
				$sites = $finder->ignoreUnreadableDirs()->in(ROOT_PATH . '/project/sites')->files()->name('*.json');
				$host = $request->getAttribute('domain');
				$found = null;

				foreach($sites as $site) {
					$site = json_decode($site->getContents(), true);

					$domain = $site['domain'];
					$languages = array_key_exists('languages', $site) ? array_values( $site['languages'] ) : [];

					if ( $domain === $host || in_array( $host, $languages ) ) {
						$found = $site;
					}
				}

				if($found) {
					return $response
						->withJson(['site' => $found])
						->withHeader('Access-Control-Allow-Origin', '*')
						->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
						->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
				}

				return $response
					->withStatus(404)
					->write('Site not found')
					->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
					->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');;
			});

			$this->options('/site/{domain}', function(Request $request, Response $response) {
				return $response
					->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
					->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
			});

			$this->put('/site/{domain}', function(Request $request, Response $response) {
				file_put_contents(ROOT_PATH . '/project/sites/' . $request->getAttribute('domain') . '.json', json_encode($request->getParsedBody()));

				return $response
					->withJson([
						'page' => json_encode($request->getParsedBody())
					])
					->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
					->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
			});

			$this->delete('/site/{domain}', function(Request $request, Response $response) {
				// The frontender knows the main domain.

				@unlink(ROOT_PATH . '/project/sites/' . $request->getAttribute('domain') . '.json');

				return $response->withStatus(204);
			});

			$this->post('/model/definition', function(Request $request, Response $response) {
				// I have post data.
				// Then we will also check if the items are available.
				$data = $request->getParsedBody();

				if(array_key_exists('name', $data)) {
					$path = ROOT_PATH . '/lib/Model/' . str_replace('\\', '/', $data['name']) . '.json';

					if(file_exists($path)) {
						return $response
							->withJson(getFileJson($path))
							->withHeader('Access-Control-Allow-Origin', '*')
							->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
							->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
					}
				}

				return $response->withStatus(404);
			});

			$this->get('/model/content', function(Request $request, Response $response) {
				$data = $request->getQueryParams();
				$name = $data['name'];
				$json = getFileJson(ROOT_PATH . '/lib/Model/' . str_replace('\\', '/', $data['name']) . '.json', true);

				unset($data['name']);

				$class =  '\\Prototype\\Model\\' . $name;
				$class = new $class($this);
				$class->setState($data);
				$result = $class->fetch();
				$state = $class->getState();
				$items = [];
				$name = $state->isUnique() ? Inflector::singularize($class->getName()) : Inflector::pluralize($class->getName());

				if(array_key_exists($name, $result) && $result[$name] && count($result[$name])) {
					$items = array_map(function($item) use ($json) {
						$item = (object) $item;

						if(!array_key_exists('map', $json)) {
							return $item;
						}

						$map = $json['map'];

						foreach($map as $target => $path) {
							$item->{$target} = array_reduce(explode('.', $path), function($carry, $key) {
								if(is_object($carry) && property_exists($carry, $key)) {
									return $carry->{$key};
								} else if(is_array($carry) && array_key_exists($key, $carry) && $carry[$key]) {
									return $carry[$key];
								}

								return false;
							}, $item);

							if(is_object($item) && !$item->{$target}) {
								$item->{$target} = '';
							} else if(is_array($item) && !$item[$target]) {
								$item[$target] = '';
							}
						}

						return (array) $item;
					}, $result[$name]);
				}

				return $response
					->withJson([
						'items' => $items,
						'state' => $result['model']->getState()->getValues(),
						'total' => $result[$name . '_total']
					])
					->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
					->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
			});

			$this->post('/thumbnail/{thumbnail}', function(Request $request, Response $response) {
				// We will save everything in the project folder.
				// So also the thumbnails.
				// Pre-generated or not.

				// Check if the directory exists.
				$thumbnail_dir = $this->settings['project']['path'] . '/thumbnails';
				if(!is_dir($thumbnail_dir)) {
					mkdir($thumbnail_dir, 0744, true);
				}

				$filename = decrypt($request->getAttribute('thumbnail'));
				$filename = str_replace('.json', '.png', $filename);
				$body = $request->getParsedBody();
				$thumbnail = $body['screenshot'];

				$basename = dirname($thumbnail_dir . '/' . $filename);
				if(!is_dir($basename)) {
					mkdir($basename, 0744, true);
				}

				$parts = explode(',', $thumbnail);
				array_shift($parts);

				file_put_contents($thumbnail_dir . '/' . $filename, base64_decode(implode(',', $parts)));

				return $response->withStatus(204);
			});

			$this->get('/thumbnail/{thumbnail}', function(Request $request, Response $response) {
				$thumbnail_dir = $this->settings['project']['path'] . '/thumbnails';
				$path = $thumbnail_dir . '/' . str_replace('.json', '', decrypt($request->getAttribute('thumbnail'))) . '.png';

				if(!is_dir($thumbnail_dir) || !file_exists($path)) {
					return $response->withStatus(404);
				}

				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$type = finfo_file($finfo, $path);
				finfo_close($finfo);

				$fh = fopen($path, 'rb');
				$stream = new \Slim\Http\Stream($fh);


				return $response->withHeader('Content-Type', $type)
				                ->withHeader('Access-Control-Allow-Origin', '*')
				                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
				                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
				                ->withHeader('bla', 'bla')
				                ->withBody($stream);
			});

			$this->group('/authenticate', function() {
				$this->options('/{username}', function(Request $request, Response $response) {
					return $response
						->withHeader('Access-Control-Allow-Origin', '*')
						->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
						->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
				});

				$this->post('/{username}', function(Request $request, Response $response) {
					// Check if the directory exists
					$path = $this->settings['project']['path'] . '/users/' . $request->getAttribute('username') . '.json';
					$query = $request->getParsedBody();

					if(!array_key_exists('password', $query) || !$request->getAttribute('username') || !is_dir(dirname($path)) || !file_exists($path)) {
						return $response->withStatus(401);
					}

					$user = getFileJson($path, true);
					$password = decrypt($query['password']);

					// Verify the password
					if(!password_verify($password, $user['password'])) {
						return $response->withStatus(401);
					}

					unset($user['password']);

					return $response->withJson($user)
					                ->withHeader('Access-Control-Allow-Origin', '*')
					                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
					                ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
				});
			});
		});

		function encrypt($data) {
			$encryptionMethod = "AES-256-CBC";
			$secret = "My32charPasswordAndInitVectorStr";  //must be 32 char length
			$iv = substr($secret, 0, 16);

			return openssl_encrypt($data, $encryptionMethod, $secret,0,$iv);
		}

		function decrypt($data) {
			$encryptionMethod = "AES-256-CBC";
			$secret = "My32charPasswordAndInitVectorStr";  //must be 32 char length
			$iv = substr($secret, 0, 16);

			return openssl_decrypt(hex_to_base64($data), $encryptionMethod, $secret,0,$iv);
		}

		function hex_to_base64($hex){
			$return = '';
			foreach(str_split($hex, 2) as $pair){
				$return .= chr(hexdec($pair));
			}
			return base64_encode($return);
		}

		function authenticate($role, $req, $res) {
			die('Called');
		}

		return $this;*/

		return $this;
	}

	public function start(  ) {
		new \Frontender\Core\Routes\App($this);

		$this->getApp()->run();

		return $this;
	}
}