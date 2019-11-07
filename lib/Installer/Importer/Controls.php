<?php

namespace Frontender\Core\Installer\Importer;

class Controls extends Generic
{
    public function import($collection, $path)
    {
        $controls = $this->getFiles($path);
        $lotsCollection = $this->adapter->collection('lots');
        $controlsCollection = $this->adapter->collection($collection);
        $team = $this->adapter->collection('teams')->findOne([
            'name' => 'Site'
        ]);

        foreach ($controls as $control) {
            $definition = json_decode($control->getContents());
            $identifier = str_replace('.' . $control->getExtension(), '', $control->getRelativePathname());

            $lot = $lotsCollection->insertOne([
                'teams' => [$team->_id->__toString()]
            ]);

            $controlsCollection->deleteMany([
                'identifier' => $identifier
            ]);

            $controlsCollection->insertOne([
                'revision' => [
                    'lot' => $lot->getInsertedId()->__toString(),
                    'date' => gmdate('c'),
                    'hash' => md5($control->getContents())
                ],
                'definition' => $definition,
                'identifier' => $identifier
            ]);
        }
    }

    public static function importViaComposer($event) {
	    defined('ROOT_PATH') || define('ROOT_PATH', getcwd());

    	$arguments = $event->getArguments();
    	$path = $arguments[0];
    	$collection = $arguments[1] ?? 'controls';

    	$instance = new Controls();
    	$instance->import($collection, $path);
    }
}
