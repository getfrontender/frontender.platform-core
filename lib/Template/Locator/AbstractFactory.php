<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Template\Locator;

use League\Flysystem\Exception;

abstract class AbstractFactory
{
    public function createLocator($type)
    {
        $locator_class = '\\Frontender\\Core\\Template\\Locator\\' . ucfirst($type) . 'Locator';

        try {
            $locator = new $locator_class();
        } catch(Exception $exception) {
            throw new \Exception();
        }

        return $locator;
    }
}
