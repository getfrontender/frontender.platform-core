<?php

namespace Frontender\Core\Wrappers;

use Doctrine\Common\Inflector\Inflector;

class Model extends Core
{
    private $model;
    private $container;

    public function __construct($model, $container)
    {
        if (is_array($model)) {
            $name = $model['name'];
            $adapter = $model['adapter'];
            unset($model['name']);
            unset($model['adapter']);

			// The rest are states.
            $model['language'] = $model['language'] ?? $container->language->get();
            $model_class = 'Prototype\\Model\\' . $adapter . '\\' . ucfirst($name) . 'Model';
            $instance = new $model_class($container);
            $instance->setState($model);
        } else if (is_object($model)) {
            $instance = $model;
        }

        $this->container = $container;
        $this->model = $instance;
    }

    public function offsetExists($offset)
    {
        $data = $this->fetch();

        return isset($data[$offset]);
    }

    public function offsetGet($offset)
    {
        $data = $this->fetch();

        return $data[$offset];
    }

    public function current()
    {
        $item = $this->data[$this->position];
        $wrapper = new self($this->model, $this->container);
        $wrapper->setData($item);

        return $wrapper;
    }

    public function valid()
    {
        $data = $this->fetch();

        return isset($data[$this->position]);
    }

    public function getData()
    {
        return $this->fetch();
    }

    private function fetch()
    {
        if ($this->data === null) {
            $this->data = $this->model->fetch();
        }

        return $this->data;
    }
}