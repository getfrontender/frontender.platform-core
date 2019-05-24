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

            $lot = $lotsCollection->insertOne([
                'teams' => [$team['_id']]
            ]);

            $this->adapter->collection($collection)->deleteMany([
                'identifier' => $identifier
            ]);

            // There is no identifier here, I add them for the uniqueness, and so we can keep a record.

            $this->adapter->collection($collection)->insertOne([
                'revision' => [
                    'hash' => md5($blueprint->getContents()),
                    'type' => $type,
                    'date' => date('c'),
                    'lot' => $lot->getInsertedId()->__toString()
                ],
                'definition' => json_decode($blueprint->getContents()),
                'identifier' => $identifier
            ]);
        }

        return true;
    }
}
