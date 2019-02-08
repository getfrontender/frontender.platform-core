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