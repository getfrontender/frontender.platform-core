<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Page;

use Doctrine\Common\Inflector\Inflector;
use Prototype\Model\AbstractModel;
use Frontender\Core\Object\MapperTrait;
use Frontender\Core\Object\Object;
use Frontender\Core\Parameters\ParametersTrait;
use Frontender\Core\Template\Template;

use Slim\Container;
use Slim\Http\Request;

class DefaultPage extends Object
{
    use ParametersTrait;
    use MapperTrait;

    protected $template;

    protected $name;

    protected $request;

    private $_parsed = false;

    public $data;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->template = new Template($container);
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    public function getRequest() : Request
    {
        return $this->request;
    }

    public function setRequest($request)
    {
        $this->request = $request;

        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    public function getModel($config, $iterator)
    {
        $model = null;

        if (is_string($config) && $config === '@inherit') {
            for ($i = $iterator->getDepth() - 1; $i >= 0; $i--) {
                $parent = $iterator->getSubIterator($i);
                $key = $parent->key();

                $data = $parent->offsetGet($key);

                if (is_object($data) && property_exists($data, 'data'))
                {
                    if (is_array($data->data) && count($data->data))
                    {
                        // TODO: Nested Inherit!
                    }
                }

                if ($i === 0)
                {
                    if ($parent->offsetExists('data'))
                    {
                        $data = $parent->offsetGet('data');

                        $name = $parent->offsetExists('model') ? $parent->offsetGet('model')->name : $this->getName();

                        if($parent->offsetExists('model')) {
                            $iterator->offsetSet('model', $parent->offsetGet('model'));
                        }

                        if(array_key_exists(Inflector::singularize($name), $data)) {
                            $model = $data[Inflector::singularize($name)];
                        }
                    }
                }
            }
        } else {
            $params = [];

            foreach($config as $key => $values) {
                if($key === 'controls') {
                    foreach($values as $itemKey => $value) {
                        $params[$itemKey] = $this->getFormValue($value);
                    }
                }
            }

            $name = $params['name'];
            unset($params['name']);

            $state = isset($params) ? $this->mapValues($params, $iterator) : [];

            // Parse the data through to the view.
            $config->params = $state;

            $state['language'] = array_key_exists('language', $state) ? $state['language'] : $this->getLocale();

            $model_class = 'Prototype\\Model\\' . ucfirst($name) . 'Model';

            if ($this->container->has($model_class)) {
                $model = $this->container->get($model_class);
                $model->setState($state, true);
            } else {
                $model = new $model_class($this->container);
                $model->setState($state);
            }
        }

        return $model;
    }

    // TODO: Allow for flexiable injection!
    public function getLocale() : string
    {
        return $this->container->language->get();
    }

    public function parseData()
    {
        if($this->_parsed === true) {
            return $this->data;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($this->data), \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $key => $values)
        {
            // Make sure to append the data key!
            if ($key === 'template')
            {
                if (!$iterator->getInnerIterator()->offsetExists('data'))
                {
                    $iterator->getInnerIterator()->offsetSet('data', []);
                }
            }

            if ($key === 'template_config')
            {
                $iterator->getSubIterator($iterator->getDepth())->offsetSet('data', (function () use ($values, $iterator) {
                    $data = $iterator->getSubIterator($iterator->getDepth())->offsetExists('data') ?
                        $iterator->getSubIterator($iterator->getDepth())->offsetGet('data') : [];

                    foreach ($values as $key => $value)
                    {
                        if ($key === 'model')
                        {
                            $model = $this->getModel($value, $iterator);

                            if ($model instanceof AbstractModel)
                            {
	                            $name = $model->getName();

	                            if(strpos($name, 'Channel') !== false) {
	                            	$name = strtolower(str_replace('Channel', '', $name));
	                            }

	                            if(property_exists($value->controls, 'id') && strpos($model->getName(), 'Channel') === false) {
	                            	$name = Inflector::singularize($name);
	                            }

	                            $data[$name] = new Wrapper($model);
                                $iterator->getSubIterator($iterator->getDepth())->offsetSet('model', $value);
                            }
                        } else {
                            // Check if we have "form" elements.
                            $data[$key] = $value;
                        }
                    }

                    return $data;
                })());

                // Remove key when finished.
                $iterator->getInnerIterator()->offsetUnset($key);
            }

            if($key === 'controls') {
                $current = $iterator->getSubIterator($iterator->getDepth());

                foreach($values as $valueKey => $value) {
                    $current->offsetSet($valueKey, $this->getFormValue($value));
                }
            }
        }

        $parsed = true;
    }

    private function getId($iterator)
    {
	    // The current iterator has no id, so we will check in the parent.
	    $id = null;
	    $depth = $iterator->getDepth();

	    for($i = $depth; $i >= 0; $i--) {
	    	$parent = $iterator->getSubIterator($i);

		    if($parent->offsetExists('model')) {
		    	$model = $parent->offsetGet('model');
			    $temp = $model->controls->id->value;

			    if($temp && !preg_match('/\{\s*(.*?)\s*\}/', $temp)) {
				    $id = $temp;
				    break;
			    }
		    }

	    	if($parent->offsetExists('template_config')) {
			    $config = $iterator->getSubIterator($i)->offsetGet('template_config');
			    $temp = $config->model->controls->id->value;

			    if($temp && !preg_match('/\{\s*(.*?)\s*\}/', $temp)) {
			    	$id = $temp;
			    	break;
			    }
		    }
	    }

	    // Check if we have a frontender request, if so return the post id, else return null;
	    if(!$id && isset($_POST['fromFrontender']) && isset($_POST['frontenderID']) && $_POST['frontenderID']) {
	    	// Check if an id is found
	        return $_POST['frontenderID'];
	    }

	    if(!$id) {
		    $parameters = $this->getParameters();
		    $id = $this->parameters['default']['id'] ?? null;

		    // Needed to get things from nested values.
		    if ( strpos( $id, '.' ) !== false ) {
			    $parts = explode( '.', $id );
			    $id = array_reduce( $parts, function ( $carry, $item ) {
				    return $carry[ $item ] ?? null;
			    }, $parameters );

		    }
	    }

	    return $id;
    }

    public function getFormValue($form)
    {
        return is_object($form) ? $form->value : $form['value'];
    }

    public function render(array $data = array()) : string
    {
        $this->parseData();

        $vars = array_merge($this->getParameters(), (array_merge($this->getData()->data, $data))) ?? [];
        $this->getTemplate()->setDefaultVariables($vars);

        $data = [];

        if(array_key_exists('containers', $this->getData())) {
            $data['containers'] = $this->getData()->containers;
        }

        return $this->template->loadFile($this->getData()->template)->render($data);
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function camalize($word)
    {
        $word = str_replace(' ', '', ucwords(strtolower(str_replace('_', ' ', $word))));

        return $word;
    }
}

class Wrapper implements \ArrayAccess, \Iterator {
	private $position = 0;
	private $model;
	private $data = null;

	public function __construct($model) {
		$this->model = $model;
	}

	public function offsetExists( $offset ) {
		// TODO: Implement offsetExists() method.
		return array_key_exists($offset, $this->fetch());
	}

	public function offsetGet( $offset ) {
		$data =  $this->fetch();

		return $data[$offset];
	}

	public function offsetSet( $offset, $value ) {
		// TODO: Implement offsetSet() method.
	}

	public function offsetUnset( $offset ) {
		// TODO: Implement offsetUnset() method.
	}

	public function setData($data) {
		$this->data = $data;
	}

	public function next() {
		++$this->position;
	}

	public function current() {
		$item = $this->data[$this->position];
		$wrapper = new Wrapper($this->model);
		$wrapper->setData($item);

		return $wrapper;
	}

	public function rewind() {
		$this->position = 0;
	}

	public function key() {
		return $this->position;
	}

	public function valid() {
		$data = $this->fetch();

		return isset($data[$this->position]);
	}

	private function fetch() {
		if($this->data === null) {
			$data = $this->model->fetch();

			$name = $this->model->getName();
			if(strpos($name, 'Channel') !== false) {
				$name = strtolower(str_replace('Channel', '', $name));
			}

			if(array_key_exists('id', $this->model->getState()->getValues(true)) && strpos($this->model->getName(), 'Channel') === false) {
				$name = Inflector::singularize($name);
			}

			error_log($name);

			$this->data = $data[$name];
		}

		return $this->data;
	}
}
