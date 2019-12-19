<?php

namespace Frontender\Core\Installer\Importer;

class Blueprints extends Generic
{
    public function import($collection, $path)
    {
        $blueprints = $this->getFiles($path);
        $lotsCollection = $this->adapter->collection('lots');
        $teams = $this->adapter->collection('teams')->find()->toArray();
        $teams = $this->adapter->toJSON($teams, true);
        $team = array_shift($teams);

        foreach ($blueprints as $blueprint) {
            $pathParts = explode('/', $blueprint->getRelativePath());
            $identifier = str_replace('.' . $blueprint->getExtension(), '', $blueprint->getRelativePathname());
            $type = array_shift($pathParts);

            // First we will check if the blueprint exists, if so we will update it, else we will create it.
	        $blueprints = $this->adapter->collection($collection)->find([
	        	'identifier' => $identifier
	        ])->toArray();

	        // Check if we have a thumbnail file.
	        $thumbnail = '';
	        $thumbnail_path = sprintf('%s/%s.png', $blueprint->getRealPath(), $blueprint->getFileName());

	        if (file_exists($thumbnail_path)) {
		        $thumbnail = 'data:image/png;base64,' . base64_encode(file_get_contents($thumbnail_path));
	        }

	        if(count($blueprints)) {
		        $this->adapter->collection($collection)->updateOne([
		        	'_id' => $blueprints[0]->_id
		        ], [
		        	'$set' => [
		        		'revision.hash' => md5($blueprint->getContents()),
				        'revision.thumbnail.en-GB' => $thumbnail,
				        'revision.date' => gmdate('c'),
				        'definition' => json_decode($blueprint->getContents())
			        ]
		        ]);
	        } else {
		        $lot = $lotsCollection->insertOne([
			        'teams' => [$team['_id']]
		        ]);

		        $this->adapter->collection($collection)->insertOne([
			        'revision' => [
				        'hash' => md5($blueprint->getContents()),
				        'thumbnail' => [
					        'en-GB' => $thumbnail
				        ],
				        'type' => $type,
				        'date' => gmdate('c'),
				        'lot' => $lot->getInsertedId()->__toString()
			        ],
			        'definition' => json_decode($blueprint->getContents()),
			        'identifier' => $identifier
		        ]);
	        }
        }

        return true;
    }

	public static function importViaComposer() {
		defined('ROOT_PATH') || define('ROOT_PATH', getcwd());
		require_once getcwd() . '/vendor/autoload.php';

		$path = getcwd() . '/project/db/blueprints';
		// Check if folder exists
		if(!file_exists($path)) {
			return 0;
		}

		$instance = new Blueprints();
		$instance->import('blueprints', $path);

		return 0;
	}
}
