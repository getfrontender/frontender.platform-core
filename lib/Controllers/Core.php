<?php

namespace Frontender\Core\Controllers;

use Frontender\Core\DB\Adapter;

class Core {
	protected $adapter;

	public function __construct() {
		$this->adapter   = Adapter::getInstance();
	}

	public function actionBrowse($filter = []) {

	}

	public function actionRead( $id ) {

	}

	public function actionEdit($id, $data) {

	}

	public function actionAdd($data) {

	}

	public function actionDelete($lot_id, $collection = 'public') {

	}

	public static function __callStatic( $name, $arguments ) {
		$class = get_called_class();
		$instance = new $class;

		return call_user_func_array([$instance, 'action' . ucfirst(strtolower($name))], $arguments);
	}
}