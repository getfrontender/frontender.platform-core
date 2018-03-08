<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

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
                return array_key_exists($key, $item) && $item[$key] != $value;
            }

            return array_key_exists($key, $item) && $item[$key] == $value;
        });
    }
}