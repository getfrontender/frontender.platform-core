<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Template\Engine;

use Frontender\Core\Object\Object;

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
use Frontender\Core\Template\Helper\Url;
use Slim\Container;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;
use Symfony\Component\Finder\Finder;

class TwigEngine extends Object implements EngineInterface
{
    protected $engine;

    protected $template;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $settings = [
            $container['settings']['template']['path'],
            [
                'cache' => $container['settings']['caching'] ? $container['settings']['template']['cache']['path'] : false,
                'debug' =>  $container['settings']['template']['debug'],
	            'auto_reload' => $container['settings']['template']['auto_reload'] ?? false
            ]
        ];

        $this->engine = new Twig(...$settings);

        if ($container['settings']['debug']) {
            $this->engine->addExtension(new \Twig_Extension_Debug());
        }

        $this->engine->addExtension(new TwigExtension($container['router'], $container['settings']['base_path']));

	    // Register the core filters
	    $this->engine->addExtension( new Date( $container ) );
	    $this->engine->addExtension( new Escaping() );
	    $this->engine->addExtension( new Filter() );
	    $this->engine->addExtension( new Humanize( $container ) );
	    $this->engine->addExtension( new Markdown() );
	    $this->engine->addExtension( new Number( $container ) );
	    $this->engine->addExtension( new Pagination() );
	    $this->engine->addExtension( new Text() );
	    $this->engine->addExtension( new Translate( $container ) );

        // Add custom extension for the engine.
	    // Auto bind all helpers
	    $finder = new Finder();
	    $finder
		    ->ignoreUnreadableDirs()
		    ->files()
		    ->in(ROOT_PATH . '/lib/Template/Filter/')
		    ->name('*.php')
		    ->sortByName();

	    foreach($finder as $file) {
		    $class = 'Prototype\\Template\\Filter\\' . $file->getBasename('.' . $file->getExtension());
		    $this->engine->addExtension( new $class( $container ) );
	    }

	    // Register the core helpers
	    $this->engine->addExtension(new Router($container));
	    $this->engine->addExtension(new HashedPath($container));
	    $this->engine->addExtension(new Url($container));

	    $finder = new Finder();
	    $finder
		    ->ignoreUnreadableDirs()
		    ->files()
		    ->in(ROOT_PATH . '/lib/Template/Helper/')
		    ->name('*.php')
		    ->sortByName();
	    foreach($finder as $file) {
		    $class = 'Prototype\\Template\\Helper\\' . $file->getBasename('.' . $file->getExtension());
		    $this->engine->addExtension( new $class( $container ) );
	    }
    }

    public function loadFile($template)
    {
        $this->template = $template;

        return $this;
    }

    public function render(array $data = array())
    {
        return $this->engine->fetch($this->template, $data);
    }
}
