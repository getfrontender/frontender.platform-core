<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Template\Engine;

use Prototype\Object\Object;

use Slim\Container;

abstract class AbstractFactory extends Object
{
    public function __construct(Container $container)
    {
        parent::__construct($container);

//        $this->populateEngines();
    }

    public function populateEngines()
    {

    }

    public function createEngine($path)
    {
//        $pathinfo = pathinfo($path);

        $engine_class = '\\Frontender\\Core\\Template\\Engine\\TwigEngine';

        $engine = new $engine_class($this->container);

        return $engine;
    }
}
