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
		return array_key_exists( $offset, $this->fetch() );
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
			$data = $this->model->fetch();

			$name = $this->model->getName();
			if ( strpos( $name, 'Channel' ) !== false ) {
				$name = strtolower( str_replace( 'Channel', '', $name ) );
			}

			if ( array_key_exists( 'id', $this->model->getState()->getValues( true ) ) && strpos( $this->model->getName(), 'Channel' ) === false ) {
				$name = Inflector::singularize( $name );
			}

			$this->data = $data[ $name ];
		}

		return $this->data;
	}
}