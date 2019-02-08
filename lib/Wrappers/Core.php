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

namespace Frontender\Core\Wrappers;

abstract class Core implements \Iterator, \ArrayAccess
{
    protected $position = 0;
    protected $data = null;

    public function setData($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet($offset)
    {
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value)
    {
        return false;
    }

    public function offsetUnset($offset)
    {
        return false;
    }

    public function valid()
    {
        return false;
    }

    public function current()
    {
        return false;
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function next()
    {
        ++$this->position;
    }

    public function key()
    {
        return $this->position;
    }
}