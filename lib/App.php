<?php
/*******************************************************
 * Copyright (C) 2017-2019 Dipity B.V., The Netherlands
 * 
 * Frontender is a web application development platform consisting of a 
 * desktop application (Frontender Desktop) and a web application which 
 * consists of a client component (Frontender Platform) and a core 
 * component (Frontender Platform Core). 
 * This file is part of Frontender Platform Core. 
 * 
 * Frontender Desktop, Frontender Platform and Frontender Platform Core
 * may not be copied and/or distributed without the express
 * permission of Dipity B.V.
 *******************************************************/

namespace Frontender\Core;

use Frontender\Core\Config\Config;
use Frontender\Core\Language\Language;
use Frontender\Core\Page\DefaultPage;
use Frontender\Core\Routes\Middleware\Page;
use Frontender\Core\Routes\Middleware\Site;
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
                    $parts = array_values(array_filter(explode('/', $request->getUri()->getPath())));
                    $locale = $parts[0] ?? 'en';
                    $page = '404';

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
        $container['config'] = $this->getConfig();

        $app->add(new Page($container));
        $app->add(new Routes\Middleware\Maintenance($container));
        $app->add(new Site($container));

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
