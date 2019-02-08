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

class Blueprints extends Core {
	public function actionBrowse($filter = []) {
		return $this->adapter->collection('blueprints')->find()->toArray();
	}

	public function actionAdd($data) {
		unset($data['_id']);
		
		$data['revision']['hash'] = md5(json_encode($data['definition']));

		return $this->adapter->collection('blueprints')->insertOne($data);
	}
}