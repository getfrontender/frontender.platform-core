<?php

namespace Frontender\Core\Wrappers;

use Doctrine\Common\Inflector\Inflector;

class Model extends Core implements \Countable
{
    private $model;
    private $container;

    public function __construct($model, $container)
    {
        if (is_array($model) && isset($model['data'])) {
            $modelData = $model['data'];
            unset($model['data']);

            $adapter = $modelData['adapter'];
            $name = $modelData['model'];

            if (isset($modelData['id'])) {
                $model['id'] = $modelData['id'];
            }

			// The rest are states.
            $model['language'] = $model['language'] ?? $container->language->get();
            $model_class = 'Prototype\\Model\\' . $adapter . '\\' . ucfirst($name) . 'Model';
            $instance = new $model_class($container);

            $instance->setState($model);
        } else if (is_object($model)) {
            $instance = $model;
        } else {
            throw new \Exception('Model config incorrect!');
        }

        $this->container = $container;
        $this->model = $instance;
    }

    public function offsetExists($offset)
    {
        $data = $this->fetch();

        return $data[0]->offsetExists($offset);
    }

    public function offsetGet($offset)
    {
        $data = $this->fetch();

        // If we have multiple data entries,
        // We will take the first, this way we will always have content.
        return $data[0]->offsetGet($offset);
    }

    public function current()
    {
        return $this->data[$this->position];
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

    private function fetch() : array
    {
        if ($this->data === null) {
            $this->data = $this->model->fetch();
        }

        return $this->data;
    }

    public function count()
    {
        $data = $this->fetch();

        return count($data);
    }
}