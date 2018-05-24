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

	public function actionSanitize($pageJson) {
		$this->_sanitizeConfig($pageJson);

		return $pageJson;
	}

	public function actionPublish($page) {
		unset($page->_id);

		$page->definition = json_decode(json_encode($page->definition), true);
		$page->definition = $this->actionSanitize($page->definition);

		/**
		 * We will only have the values or a reference to the cached values here.
		 */
		
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
		// This method will check if there is template_config if so, it will sanitize it.
		// This is because we will only need the values here.
		if(!isset($container['template_config']) && isset($container['blueprint'])) {
			// Get the blueprint from the DB.
			$blueprint = $this->adapter->collection('blueprints')->findOne([
				'_id' => new ObjectId($container['blueprint'])
			]);
			$blueprint = json_decode(json_encode($this->adapter->toJSON($blueprint)), true);
			$container['template'] = $blueprint['definition']['template'];
			$container['template_config'] = $blueprint['definition']['template_config'];
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