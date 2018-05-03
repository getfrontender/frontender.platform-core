<?php

namespace Frontender\Core\DB;

use function MongoDB\BSON\fromPHP;
use function MongoDB\BSON\toJSON;

class Adapter extends \MongoDB\Client {
	static private $instance = null;

	public function __construct() {
		parent::__construct( 'mongodb://' . $_ENV['MONGO_HOST'] . ':' . $_ENV['MONGO_PORT'] . '/' );
	}

	public function collection( $collection ) {
		return $this->selectCollection( $_ENV['MONGO_DB'], $collection );
	}

	public function toJSON( $docs, $assoc = false ) {
		if ( is_array( $docs ) ) {
			return array_map( function ( $document ) use ( $assoc ) {
				return json_decode( toJSON( fromPHP( $document ) ), $assoc );
			}, $docs );
		}

		return json_decode( toJSON( fromPHP( $docs ) ), $assoc );
	}

	static public function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new Adapter();
		}

		return self::$instance;
	}
}