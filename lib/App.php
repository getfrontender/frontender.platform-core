<?php

namespace Frontender\Core;

use Frontender\Core\Config\Config;
use Frontender\Core\Language\Language;
use Frontender\Core\Page\DefaultPage;
use Frontender\Core\Routes\Middleware\Page;
use Frontender\Core\Routes\Middleware\Sitable;
use Frontender\Core\Translate\Translate;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Slim\Http\Request;
use Slim\Http\Response;
use Doctrine\Common\Inflector\Inflector;

function getFileJson($file, $assoc = false)
{
    return json_decode(file_get_contents($file), $assoc);
}

class App
{
    private $appInstance = null;
    private $configInstance = null;

    public function getConfig()
    {
        if ($this->configInstance === null) {
            $this->configInstance = new Config();
        }

        return $this->configInstance;
    }

    public function getApp()
    {
        if ($this->appInstance === null) {
            $config = $this->getConfig();
            $this->appInstance = new \Slim\App($config->toArray());
        }

        return $this->appInstance;
    }

    public function getContainer()
    {
        $app = $this->getApp();
        return $app->getContainer();
    }

    private function _appendDebug()
    {
        $config = $this->getConfig();
        $container = $this->getContainer();

        if (!$config->debug) {
            $container['notFoundHandler'] = $container['errorHandler'] = function ($container) {
                return function (Request $request, Response $response, $exception = null) use ($container) {
                    echo '<pre>';
                    print_r($exception->getMessage());
                    echo '</pre>';
                    die();

                    $parts = array_values(array_filter(explode('/', $request->getUri()->getPath())));
                    $locale = $parts[0] ?? 'en';
                    $page = '404';

                    if (($route = $this->_tryRedirectNotFound($request->getUri())) !== false) {
                        return $response->withRedirect($route);
                    }

                    $container->language->set($locale);
                    $data = getFileJson($container->settings['project']['path'] . '/pages/published/' . $page . '.json', true);

                    $page = $container->page;
                    $page->setParameters(['locale' => $locale, 'debug' => $container->settings['debug'], 'query' => $request->getQueryParams()]);
                    $page->setData($data);
                    $page->setRequest($request);

                    return $response->write($page->render());
                };
            };
        }
    }

    private function _appendMiddleware()
    {
        $app = $this->getApp();
        $container = $this->getContainer();

        $app->add(new Page($container));
        $app->add(new Routes\Middleware\Maintenance($container));
        $app->add(new Sitable($container));

        /**
         * This will add the cors headers on every request, still needs to be a little more strict though.
         */
        $app->add(function (Request $req, Response $res, $next) {
            $response = $next($req, $res);

            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-Token, Cache-Control, Link')
                ->withHeader('Access-Control-Expose-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-Token, Cache-Control, Link')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        });
    }

    private function _appendContainerData()
    {
        $container = $this->getContainer();

        $container['language'] = function () {
            return new Language();
        };

        $container['page'] = function ($container) {
            return new DefaultPage($container);
        };

        $container['translate'] = function ($container) {
            return new Translate($container);
        };
    }

    private function _tryRedirectNotFound($url)
    {
		// Check if the redirect file can be found.
        $config = $this->getConfig();
        $project = $config->project;
        $path = $url->getPath();

        if (file_exists($project['path'] . '/redirects.json')) {
            $redirects = json_decode(file_get_contents($project['path'] . '/redirects.json'), true);

            if (array_key_exists('static', $redirects)) {
                foreach ($redirects['static'] as $source => $destination) {
                    if ($source === $path) {
                        if (preg_match('/^http[s]?/', $destination) == true) {
                            return $destination;
                        }

                        return $url->withPath($destination);
                    }
                }
            }

            if (array_key_exists('dynamic', $redirects)) {
                foreach ($redirects['dynamic'] as $regex => $replace) {
                    if (preg_match($regex, $path) == true) {
                        $path = preg_replace($regex, $replace, $path);

                        if (preg_match('/^http[s]?/', $path) == true) {
                            return $path;
                        }

                        return $url->withPath($path);
                    }
                }
            }
        }

        return false;
    }

    public function init()
    {
        $this->_appendDebug();
        $this->_appendMiddleware();
        $this->_appendContainerData();

        $config = $this->getConfig();
        $project = $config->project;

        foreach (glob(__DIR__ . '/Routes/Api/*') as $file) {
            $name = str_replace('.php', '', basename($file));
            $class = 'Frontender\\Core\\Routes\\Api\\' . $name;

            new $class($this);
        }

        try {
            $finder = new Finder();
            $files = $finder->files()->in(dirname($project['path']) . '/lib/Routes/Api')->name('*.php');

            foreach ($files as $file) {
                $namespace = str_replace(DIRECTORY_SEPARATOR, '\\', $file->getRelativePath());

                $name = str_replace('.php', '', $file->getBasename());
                $class = 'Prototype\\Routes\\Api\\' . $namespace . '\\' . $name;

                new $class($this);
            }
        } catch (\Error $e) {
        } catch (\Exception $e) {
        }

        return $this;
    }

    public function start()
    {
        new \Frontender\Core\Routes\App($this);

        $this->getApp()->run();

        return $this;
    }
}