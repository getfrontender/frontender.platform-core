<?php

/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Parameters;

trait ParametersTrait
{
    public $parameters = [];

    public function setParameters(array $parameters, $registry = 'default')
    {
        foreach ($parameters as $name => $value) {
            $match = false;

            if (is_string($value)) {
                preg_match('/\{\s*(.*?)\s*\}/', $value, $match);
            }

            $method = 'get' . ucfirst($name);

            // TODO: Add is_callable check;
            if ($match && is_callable($value)) {
//                die('setParameters');
            } else if ($match && method_exists($this, $method)) {
                $this->parameters[$registry][$name] = call_user_func_array([$this, $method], []);
            } else if ($match) {
                if (isset($this->parameters[$registry][end($match)])) {
                    $this->parameters[$registry][$name] = $this->parameters[$registry][end($match)];
                }
            } else {
                $this->parameters[$registry][$name] = $value;
            }
        }

        return $this;
    }

    public function addParameters(array $parameters, $registry = 'default')
    {
        $this->parameters[$registry] = array_merge($this->getParameters($registry), $parameters);

        return $this;
    }

    public function getParameters($registry = 'default')
    {
        return isset($this->parameters[$registry]) ? $this->parameters['default'] : [];
    }
}
