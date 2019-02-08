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
