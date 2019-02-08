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

use Frontender\Core\Object\Object;

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
