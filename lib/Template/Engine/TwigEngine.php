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

namespace Frontender\Core\Template\Engine;

use Frontender\Core\Template\Engine\Twig\Environment;
use Frontender\Core\Template\Filter\Date;
use Frontender\Core\Template\Filter\Escaping;
use Frontender\Core\Template\Filter\Filter;
use Frontender\Core\Template\Filter\Humanize;
use Frontender\Core\Template\Filter\Markdown;
use Frontender\Core\Template\Filter\Number;
use Frontender\Core\Template\Filter\Pagination;
use Frontender\Core\Template\Filter\Text;
use Frontender\Core\Template\Filter\Translate;
use Frontender\Core\Template\Helper\HashedPath;
use Frontender\Core\Template\Helper\Router;
use Frontender\Core\Template\Filter\Asset;
use Frontender\Core\Template\Helper\Url;
use Slim\Container;
use Slim\Views\TwigExtension;
use Symfony\Component\Finder\Finder;
use Frontender\Core\Template\Helper\Template;
use Frontender\Core\Object\AbstractObject;

class TwigEngine extends AbstractObject implements EngineInterface
{
    protected $engine;

    protected $template;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $loader = new \Twig_Loader_Filesystem($container['settings']['template']['path']);
        $this->engine = new Environment($loader, [
            'cache' => $container['settings']['caching'] ? $container['settings']['template']['cache']['path'] : false,
            'debug' => $container['settings']['template']['debug'],
            'auto_reload' => $container['settings']['template']['auto_reload'] ?? false
        ]);

        if ($container['settings']['debug']) {
            $this->engine->addExtension(new \Twig_Extension_Debug());
        }

        $this->engine->addExtension(new TwigExtension($container['router'], $container['settings']['base_path']));

        // Register the core filters
        $this->engine->addExtension(new Date($container));
        $this->engine->addExtension(new Escaping($container));
        $this->engine->addExtension(new Asset($container));
        $this->engine->addExtension(new Filter());
        $this->engine->addExtension(new Humanize($container));
        $this->engine->addExtension(new Markdown());
        $this->engine->addExtension(new Number($container));
        $this->engine->addExtension(new Pagination());
        $this->engine->addExtension(new Text());
        $this->engine->addExtension(new Translate($container));

        // Add custom extension for the engine.
        // Auto bind all helpers
        if (file_exists(ROOT_PATH . '/lib/Template/Filter')) {
            $finder = new Finder();
            $finder
                ->ignoreUnreadableDirs()
                ->files()
                ->in(ROOT_PATH . '/lib/Template/Filter/')
                ->name('*.php')
                ->sortByName();

            foreach ($finder as $file) {
                $class = 'Prototype\\Template\\Filter\\' . $file->getBasename('.' . $file->getExtension());
                $this->engine->addExtension(new $class($container));
            }
        }

        // Register the core helpers
        $this->engine->addExtension(new Router($container));
        $this->engine->addExtension(new HashedPath($container));
        $this->engine->addExtension(new Url($container));
        $this->engine->addExtension(new Template($container));

        if (file_exists(ROOT_PATH . '/lib/Template/Helper')) {
            $finder = new Finder();
            $finder
                ->ignoreUnreadableDirs()
                ->files()
                ->in(ROOT_PATH . '/lib/Template/Helper/')
                ->name('*.php')
                ->sortByName();

            foreach ($finder as $file) {
                $class = 'Prototype\\Template\\Helper\\' . $file->getBasename('.' . $file->getExtension());
                $this->engine->addExtension(new $class($container));
            }
        }
    }

    public function loadFile($template)
    {
        $this->template = $template;

        return $this;
    }

    public function render(array $data = array())
    {
        return $this->engine->render($this->template, $data);
    }
}
