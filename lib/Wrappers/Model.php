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

use Doctrine\Common\Inflector\Inflector;
use Frontender\Core\Template\Filter\Translate;

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
            $model_class = 'Frontender\\Platform\\Model\\' . $adapter . '\\' . ucfirst($name) . 'Model';
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

        if (isset($data[0])) {
            return $data[0]->offsetExists($offset);
        }

        return false;
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

    private function fetch(): array
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
