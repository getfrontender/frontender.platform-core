<?php

namespace Frontender\Core\Wrappers;

abstract class Core implements \Iterator, \ArrayAccess {
	private $position = 0;
	protected $data = null;

	public function setData( $data ) {
		$this->data = $data;
	}

	public function offsetExists( $offset ) {
		return array_key_exists($offset, $this->data);
	}

	public function offsetGet( $offset ) {
		return $this->data[$offset];
	}

	public function offsetSet( $offset, $value ) {
		return false;
	}

	public function offsetUnset( $offset ) {
		return false;
	}

	public function valid() {
		return false;
	}

	public function current() {
		return false;
	}

	public function rewind() {
		$this->position = 0;
	}

	public function next() {
		++$this->position;
	}

	public function key() {
		return $this->position;
	}
}