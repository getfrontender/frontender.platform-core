<?php

namespace Frontender\Core\Controllers;

use MongoDB\BSON\ObjectId;

class Pages extends Core {
	public function actionBrowse($filter = []) {
		$collection = isset($filter['collection']) ? 'pages.' . $filter['collection'] : 'pages';
		$findFilter = [];

		if(isset($filter['lot'])) {
			$findFilter['revision.lot'] = $filter['lot'];
		}

		return $this->adapter->collection($collection)->find($findFilter)->toArray();
	}

	public function actionRead( $id ) {
		return $this->adapter->collection('pages')->findOne([
			'_id' => new ObjectId($id)
		]);
	}
	
	public function actionEdit($id, $data) {
		unset($data['_id']);

		$data = $this->adapter->collection('pages')->findOneAndReplace([
			'revision.lot' => $id
		], $data, [
			'returnNewDocument' => true,
			'upsert' => true
		]);

		return $data;
	}

	public function actionAdd($item, $collection = 'pages') {
		return $this->adapter->collection($collection)->insertOne($item);
	}

	public function actionPublish($page) {
		unset($page->_id);

		return $this->adapter->collection('pages.public')->findOneAndReplace([
			'revision.lot' => $page->revision->lot
		], $page, [
			'upsert' => true
		]);
	}

	public function actionDelete($lot_id, $collection = 'public') {
		$collection = 'pages' . ($collection ? '.' . $collection : '');

		$this->adapter->collection($collection)->deleteOne([
			'revision.lot' => $lot_id
		]);

		return true;
	}
}