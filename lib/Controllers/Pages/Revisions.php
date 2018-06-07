<?php

namespace Frontender\Core\Controllers\Pages;

use Frontender\Core\Controllers\Core;
use MongoDB\Driver\Query;

class Revisions extends Core {
	public function actionAdd( $data ) {
		unset($data['_id']);

		$data['revision']['date'] = gmdate('Y-m-d\TH:i:s\Z');
		$data['revision']['hash'] = sha1(json_encode($data['definition']) . time());

		$result = $this->adapter->collection('pages')->insertOne($data);
		$data['_id'] = $result->getInsertedId()->__toString();

		return $data;
	}

	public function actionRead( $lot_id, $revision = 'last', $sort = 1 ) {
		$collection = 'pages' . ($revision === 'public' ? '.public' : '');
		$sort = $revision === 'last' ? -1 : $sort;

		$query = new Query([
			'revision.lot' => $lot_id
		], ['sort' => [
			'revision.date' => $sort
		]]);
		$revisions = $this->adapter->getManager()->executeQuery($_ENV['MONGO_DB'] . '.' . $collection, $query)->toArray();

		if($revision === 'all') {
			return $revisions;
		}
		
		return array_shift($revisions);
	}

	public function actionDelete($lot_id, $collection = 'public') {
		parent::actionDelete($lot_id, $collection);

		// When a revision is removed it will be moved.
		$array = $this->adapter->collection('pages')->find([
			'revision.lot' => $lot_id
		])->toArray();

		$this->adapter->collection('pages.trash')->insertMany($array);
		$this->adapter->collection('pages')->deleteMany([
			'revision.lot' => $lot_id
		]);

		return true;
	}
}