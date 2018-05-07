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
		if(!$docs) {
			return $docs;
		}

		if ( is_array( $docs ) ) {
			return array_map( function ( $document ) use ( $assoc ) {
				$document = json_decode( toJSON( fromPHP( $document ) ), $assoc );
				$this->replaceObjectID($document, $assoc);

				return $document;
			}, $docs );
		}

		$document = json_decode( toJSON( fromPHP( $docs ) ), $assoc );
		$this->replaceObjectID($document, $assoc);
		return $document;
	}

	private function replaceObjectID(&$document, $isArray) {
		// Check if we have an object or an array.
		foreach($document as $key => &$value) {
			if(!$isArray && is_object($value) && property_exists($value, '$oid')) {
				$document->{$key} = $value->{'$oid'};
			} else if(!$isArray && is_object($value)) {
				$this->replaceObjectID($value, $isArray);
			} else if($isArray && is_array($value) && array_key_exists('$oid', $value)) {
				$document[$key] = $value['$oid'];
			} else if($isArray && is_array($value)) {
				$document[$key] = $this->replaceObjectID($value, $isArray);
			}
		}

		return $document;
	}

	static public function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new Adapter();
		}

		return self::$instance;
	}
}