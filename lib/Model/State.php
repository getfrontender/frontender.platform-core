<?php

namespace Frontender\Core\Model;

class State
{
    private $data = [];

    public function insert($name, $default = null, $unique = false)
    {
        //Create the state
        $state = new \stdClass();

        $state->name = $name;
        $state->value = $default;
        $state->unique = $unique;

        $this->data[$name] = $state;

        return $this;
    }

    public function __get($key)
    {
        if (isset($this->{$key})) {
            return $this->data[$key]->value;
        }

        return null;
    }

    public function __isset($key)
    {
        return array_key_exists($key, $this->data);
    }

    public function __set($key, $value = null)
    {
        if (isset($this->{$key})) {
            $this->data[$key]->value = $value;
        }
    }

    public function remove($name)
    {
        $this->offsetUnset($name);
        return $this;
    }

    public function setValues(array $data)
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }

        return $this;
    }

    public function getValues($unique = false)
    {
        $states = $this->data;

        if ($unique == true) {
            $states = array_filter($states, function ($state) {
                return $state->unique;
            });
        }

        $states = array_filter($states, function ($state) {
            return $state->value;
        });

        return array_map(function ($state) {
            return $state->value;
        }, $states);
    }

    /**
     * Checks if all the unique values of the state are set.
     * 
     * @return boolean
     */
    public function isUnique()
    {
        // Get all the unique states
        $states = array_filter($this->data, function ($state) {
            return $state->unique;
        });

        // Get all the states with a value.
        $filled = array_filter($states, function ($state) {
            return $state->value;
        });

        return count($states) === count($filled);
    }

    public function __clone()
    {
        $state = new State();

        foreach ($this->data as $key => $item) {
            $state->insert($key, $item->value, $item->unique);
        }

        return $state;
    }
}
