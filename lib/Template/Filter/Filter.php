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

namespace Frontender\Core\Template\Filter;

use Slim\Container;

class Filter extends \Twig_Extension
{
    public function getFilters()
    {
        return [
            new \Twig_Filter('filterArray', [$this, 'filterArray'])
        ];
    }

    public function filterArray($array, $key, $value = null, $matchUnequal = false)
    {
        if(!is_array($array)) {
            return false;
        }

        return array_filter($array, function($item) use($key, $value, $matchUnequal) {
            if(!$value) {
                return array_key_exists($key, $item) && !empty($item[$key]);
            }

            if($matchUnequal) {
            	// If the key doesn't the value doesn't match, so we keep it.
	            if(!isset($item[$key])) {
	            	return true;
	            }

	            // The key exists, so we check the value.
                return $item[$key] != $value;
            }

            return array_key_exists($key, $item) && $item[$key] == $value;
        });
    }
}