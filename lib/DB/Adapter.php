<?php

namespace Frontender\Core\DB;

class Adapter extends \MongoDB\Client {
	static private $instance = null;

	public function __construct() {
		parent::__construct('mongodb://' . $_ENV['MONGO_HOST'] . ':' . $_ENV['MONGO_PORT'] . '/');
	}

	public function collection($collection) {
		return $this->selectCollection($_ENV['MONGO_DB'], $collection);
	}

	static public function getInstance() {
		if(self::$instance === null) {
			self::$instance = new Adapter();
		}

		return self::$instance;
	}
}