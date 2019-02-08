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

class ObjectArray extends Object implements \IteratorAggregate, \ArrayAccess, \Serializable, \Countable
{
    protected $data = [];

    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    public function offsetGet($offset)
    {
        $result = null;

        if (isset($this->data[$offset])) {
            $result = $this->data[$offset];
        }

        return $result;
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->data);
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    public function serialize()
    {
        return serialize($this->data);
    }

    public function unserialize($data)
    {
        $this->data = unserialize($data);
    }

    public function count()
    {
        return count($this->data);
    }

    public function fromArray(array $data)
    {
        $this->data = $data;

        return $this;
    }

    public function toArray()
    {
        return $this->data;
    }

    final public function __get($key)
    {
        return $this->offsetGet($key);
    }

    final public function __set($key, $value)
    {
        $this->offsetSet($key, $value);
    }

    final public function __isset($key)
    {
        return $this->offsetExists($key);
    }

    final public function __unset($key)
    {
        $this->offsetUnset($key);
    }
}
