<?php

namespace Frontender\Core\Installer\Importer;

use Frontender\Core\DB\Adapter;
use Symfony\Component\Finder\Finder;

class Generic
{
    protected $adapter;

    public function __construct()
    {
        $this->adapter = Adapter::getInstance();
    }

    public function getSettings()
    {
        $settings = $this->adapter->collection('settings')->find()->toArray();
        $settings = array_shift($settings);

        return $this->adapter->toJSON($settings, true);
    }

    protected function getFiles($path)
    {
        $finder = new Finder();
        return $finder->files()->in($path)->name('*.json');
    }

    public function import($collection, $path)
    {
        $files = $this->getFiles($path);
        $collection = $this->adapter->collection($collection);

        foreach ($files as $file) {
            $collection->insertOne(json_decode($file->getContents()));
        }
    }
}
