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


            // Check if we have a thumbnail file.
            $thumbnail = '';
            $thumbnail_path = sprintf('%s/%s.png', $blueprint->getRealPath(), $blueprint->getFileName());

            if (file_exists($thumbnail_path)) {
                $thumbnail = 'data:image/png;base64,' . base64_encode(file_get_contents($thumbnail_path));
            }

            $this->adapter->collection($collection)->insertOne([
                'revision' => [
                    'hash' => md5($blueprint->getContents()),
                    'thumbnail' => [
                        'en-GB' => $thumbnail
                    ],
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
