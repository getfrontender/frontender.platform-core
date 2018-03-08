<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

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