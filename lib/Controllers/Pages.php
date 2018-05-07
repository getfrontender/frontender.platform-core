<?php

namespace Frontender\Core\Controllers;

class Pages extends Core {
	public function actionBrowse() {
		return $this->adapter->collection('pages.public')->find()->toArray();
	}

	public function actionRead( $id ) {

	}
	
	public function actionEdit($id, $data) {
		unset($data['_id']);

		$data = Adapter::getInstance()->collection('pages')->findOneAndReplace([
			'revision.lot' => $id
		], $data, [
			'returnNewDocument' => true,
			'upsert' => true
		]);

		return $data;
	}
}