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

namespace Frontender\Core\Object;

trait MapperTrait
{
    public function mapValues($data, $iterator = null)
    {
        $result = [];

        foreach ($data as $name => $value) {
            if (is_string($value)) {
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
                if (strpos($value, '.') !== false) {
                    $parts = explode('.', end($match));
                    $value = array_reduce($parts, function ($carry, $item) {
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
