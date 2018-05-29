<?php

namespace Frontender\Core\Controllers;

use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Query;

class Pages extends Core {
	public function actionBrowse($filter = []) {
		$collection = isset($filter['collection']) ? 'pages.' . $filter['collection'] : 'pages';
		$findFilter = new \stdClass();

		if(isset($filter['lot'])) {
			$findFilter->{'revision.lot'} = $filter['lot'];
		}

		$revisions = $this->adapter->collection($collection)->aggregate([
			[ '$sort' => [
				'revision.date' => -1
			]],
			['$group' => [
				'_id' => '$revision.lot',
				'uuid' => [
					'$first' => '$_id'
				],
				'revision' => [
					'$first' => '$revision'
				],
				'definition' => [
					'$first' => '$definition'
				],
				'amount' => [
					'$sum' => 1
				]
			]],
			[ '$match' => $findFilter]
		])->toArray();

		return array_map(function($revision) {
			$revision['_id'] = $revision['uuid'];
			unset($revision['uuid']);

			return $revision;
		}, $revisions);
	}

	public function actionRead( $id ) {
		return $this->adapter->collection('pages')->findOne([
			'_id' => new ObjectId($id)
		]);
	}
	
	public function actionEdit($id, $data) {
		unset($data['_id']);

		$data['revision']['hash'] = md5(json_encode($data['definition']));

		$data = $this->adapter->collection('pages')->findOneAndReplace([
			'revision.lot' => $id
		], $data, [
			'returnNewDocument' => true,
			'upsert' => true
		]);

		return $data;
	}

	public function actionAdd($item, $collection = 'pages') {
		$item['revision']['hash'] = md5(json_encode($item['definition']));
		$item['devision']['date'] = gmdate('Y-m-d\TH:i:s\Z');

		return $this->adapter->collection($collection)->insertOne($item);
	}

	public function actionSanitize($pageJson) {
		$this->_sanitizeConfig($pageJson);

		return $pageJson;
	}

	public function actionPublish($page) {
		unset($page->_id);

		$page->definition = json_decode(json_encode($page->definition), true);
		$page->definition = $this->actionSanitize($page->definition);
		
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

	private function _sanitizeConfig(&$container) {
		if(!isset($container['template_config']) && isset($container['blueprint'])) {
			$blueprint = $this->adapter->collection('blueprints')->findOne([
				'_id' => new ObjectId($container['blueprint'])
			]);
			$blueprint = json_decode(json_encode($this->adapter->toJSON($blueprint)), true);
			$container['template'] = $blueprint['definition']['template'];
			$container['template_config'] = $blueprint['definition']['template_config'];
			$container['fe-id'] = $blueprint['definition']['fe-id'];
		}

		if(isset($container['template_config'])) {
			$newConfig = [];

			foreach($container['template_config'] as $key => $section) {
				$newSection = [];

				foreach($section['controls'] as $index => $control) {
					if(isset($control['value'])) {
						$newSection[ $index ] = $control['value'];
					}
				}

				$newConfig[$key] = $newSection;
			}

			$container['template_config'] = $newConfig;
		}

		if(isset($container['containers'])) {
			foreach($container['containers'] as &$subContainer) {
				$this->_sanitizeConfig($subContainer);
			}
		}
	}
}