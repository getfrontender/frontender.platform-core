<?php

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