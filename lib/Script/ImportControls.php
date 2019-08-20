<?php

namespace Frontender\Core\Script;

use Symfony\Component\Finder\Finder;
use Composer\Script\Event;
use Frontender\Core\DB\Adapter;

class ImportControls extends Base
{
    public static function import(Event $event)
    {
        self::loadEnv();

        $arguments = $event->getArguments();
        $finder = new Finder();

        if(!count($arguments)) {
            echo 'Please provide the directory for control lookup as first argument.';
            echo "\r\n";
            die();
        }

        $files = $finder->files()->in($arguments[0])->name('*.json');
        foreach($files as $file) {
            // If there are already controls with the current identifier found we will not do anything.
            $identifier = str_replace('.' . $file->getExtension(), '', $file->getRelativePathname());
            $controls = Adapter::getInstance()->collection('controls')->find([
                'identifier' => $identifier
            ]);

            if(count($controls)) {
                continue;
            }

            $team = Adapter::getInstance()->collection('teams')->findOne();
            $lot = Adapter::getInstance()->collection('lot')->insertOne([
                'teams' => [
                    $team->_id->__toString()
                ],
                'created' => time()
            ]);

            Adapter::getInstance()->collection('controls')->insertOne([
                'revision' => [
                    'date' => gmdate('c'),
                    'hash' => md5($file->getContents()),
                    'lot' => $lot->getInsertedId()->__toString()
                ],
                'definition' => json_decode($file->getContents()),
                'identifier' => $identifier
            ]);
        }

        echo 'All done!';
        echo "\r\n";
    }
}