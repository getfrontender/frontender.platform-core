<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Object;

trait MapperTrait
{
    public function mapValues($data, $iterator)
    {
        $result = [];

        foreach ($data as $name => $value)
        {
            if(is_string($value)) {
                preg_match('/\{\s*(.*?)\s*\}/', $value, $match);
            } else {
                $match = [];
            }

            $method = 'get' . ucfirst($name);

            // TODO: Add is_callable check;
            if ($match && is_callable($value)) {

            } else if ($match && method_exists($this, $method)) {
                $result[$name] = call_user_func_array([$this, $method], [$iterator]);
            } else if ($match && method_exists($this, 'getParameters')) {
                $parameters = $this->getParameters();

                // Needed to get things from nested values.
                if(strpos($value, '.') !== false) {
                    $parts = explode('.', end($match));
                    $value = array_reduce($parts, function($carry, $item) {
                        return $carry[$item] ?? null;
                    }, $parameters);

                    $result[$name] = $value;
                } else if (isset($parameters[end($match)])) {
                    $result[$name] = $this->parameters['default'][end($match)];
                }
            } else {
                $result[$name] = $value;
            }
        }

        return $result;
    }
}
