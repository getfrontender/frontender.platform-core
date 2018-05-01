<?php

namespace Frontender\Core\Wrappers;

class Config extends Core {
	public function __construct($values) {
		$this->data = $values;
	}
}