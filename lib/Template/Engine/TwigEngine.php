<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Template\Engine;

use Frontender\Core\Object\Object;

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
                'debug' =>  $container['settings']['template']['debug']
            ]
        ];

        $this->engine = new Twig(...$settings);

        if ($container['settings']['debug']) {
            $this->engine->addExtension(new \Twig_Extension_Debug());
        }

        $this->engine->addExtension(new TwigExtension($container['router'], $container['settings']['base_path']));

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
