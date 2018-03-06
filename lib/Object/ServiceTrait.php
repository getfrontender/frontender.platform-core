<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Object;

use Slim\Container;

trait ServiceTrait
{
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }
}
