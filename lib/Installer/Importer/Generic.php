<?php

namespace Frontender\Core\Installer\Importer;

use Frontender\Core\DB\Adapter;
use Symfony\Component\Finder\Finder;

class Generic {
	protected $adapter;

	public function __construct() {
		$this->adapter = Adapter::getInstance();
	}

	public function getSettings() {
		$settings = $this->adapter->collection( 'settings' )->find()->toArray();
		$settings = array_shift( $settings );

		return $this->adapter->toJSON( $settings, true );
	}

	protected function getFiles( $path ) {
		$finder = new Finder();

		return $finder->files()->in( $path )->name( '*.json' );
	}

	public function import( $collection, $path ) {
		$files      = $this->getFiles( $path );
		$collection = $this->adapter->collection( $collection );

		foreach ( $files as $file ) {
			$collection->insertOne( json_decode( $file->getContents() ) );
		}
	}

	public function getThumbnail( \SplFileInfo $path ): string {
		$thumbnail_path = sprintf( '%s/%s.png', $path->getPath(), $path->getBasename( '.' . $path->getExtension() ) );

		if(!file_exists($thumbnail_path)) {
			return '';
		}

		try {
			// Now we will resize the thumbnail to 300px width, as this is our limit.
			// If it is smaller it will become bigger, else it will become smaller.
			$image = imagecreatefrompng( $thumbnail_path );
			list( $width, $height ) = getimagesize( $thumbnail_path );

			$newHeight = ( $height / $width ) * 300;
			$tmp       = imagecreatetruecolor( 300, $newHeight );
			imagecopyresampled( $tmp, $image, 0, 0, 0, 0, 300, $newHeight, $width, $height );

			ob_start();
			imagepng($tmp);
			$image_data = ob_get_contents();
			ob_end_clean();

			return 'data:image/png;base64,' . base64_encode( $image_data );
		} catch(\Error $e) {
			error_log($e->getMessage());
			return '';
		} catch(\Exception $e) {
			error_log($e->getMessage());
			return '';
		}
	}
}
