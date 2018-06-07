<?php

namespace Frontender\Core\Wrappers;

use Doctrine\Common\Inflector\Inflector;

class Model extends Core {
	private $model;

	public function __construct( $model, $container ) {
		$name = $model['name'];
		unset($model['name']);

		// The rest are states.
		$model['language'] = $model['language'] ?? $container->language->get();
		$model_class = 'Prototype\\Model\\' . ucfirst($name) . 'Model';
		$instance = new $model_class($container);
		$instance->setState($model);

		$this->model = $instance;
	}

	public function offsetExists( $offset ) {
		$data = $this->fetch();

		return isset($data[$offset]);
	}

	public function offsetGet( $offset ) {
		$data = $this->fetch();
		
		return $data[ $offset ];
	}

	public function current() {
		$item    = $this->data[ $this->position ];
		$wrapper = new Wrapper( $this->model );
		$wrapper->setData( $item );

		return $wrapper;
	}

	public function valid() {
		$data = $this->fetch();

		return isset( $data[ $this->position ] );
	}

	private function fetch() {
		if ( $this->data === null ) {
			$this->data = $this->model->fetch();
		}

		return $this->data;
	}
}