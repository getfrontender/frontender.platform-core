<?php

namespace Frontender\Core\Controllers;

class Controls extends Core {
	public function actionBrowse($filter = []) {
		return $this->adapter->collection('controls')->find()->toArray();
	}
}