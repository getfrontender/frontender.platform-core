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

namespace Frontender\Core\Template\Helper;

use Slim\Container;

class HashedPath extends \Twig_Extension
{
    public $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('hashedPath', [$this, 'hashedPath'])
        ];
    }

    public function hashedPath($path)
    {
        $timestamp = filemtime(getcwd() . $path);
        return $path . '?' . $timestamp;
    }
}