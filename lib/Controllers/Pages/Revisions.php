<?php

namespace Frontender\Core\Controllers\Pages;

use Frontender\Core\Controllers\Core;
use MongoDB\Driver\Query;

class Revisions extends Core {
	public function actionAdd( $data ) {
		unset($data['_id']);

		$data['revision']['date'] = gmdate('Y-m-d\TH:i:s\Z');

		$result = $this->adapter->collection('pages')->insertOne($data);
		$data['_id'] = $result->getInsertedId()->__toString();

		return $data;
	}

	public function actionRead( $lot_id, $revision = 'last' ) {
		$collection = 'pages' . ($revision === 'public' ? '.public' : '');

		$query = new Query([
			'revision.lot' => $lot_id
		], ['sort' => [
			'revision.date' => $revision === 'last' ? -1 : 1
		]]);
		$revisions = $this->adapter->getManager()->executeQuery($_ENV['MONGO_DB'] . '.' . $collection, $query)->toArray();

		if($revision === 'all') {
			return $revisions;
		}
		
		return array_shift($revisions);
	}

	public function actionDelete($lot_id, $collection = 'public') {
		parent::actionDelete();

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