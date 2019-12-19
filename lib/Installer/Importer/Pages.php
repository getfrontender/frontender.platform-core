<?php

namespace Frontender\Core\Installer\Importer;

use Frontender\Core\Utils\Scopes;

class Pages extends Generic
{
    public function import($collection, $path)
    {
        $pages = $this->getFiles($path);
        $settings = $this->getSettings();
        $localeList = array_map(function ($scope) {
            return $scope['locale'];
        }, Scopes::get());
        $defaultLocale = $localeList[0] ?? 'en-GB';
        $teams = $this->adapter->collection('teams')->find()->toArray();
        $teams = $this->adapter->toJSON($teams, true);
        $team = array_shift($teams);

        $lotsCollection = $this->adapter->collection('lots');
        $pagesCollection = $this->adapter->collection('pages');
        $pagesPublicCollection = $this->adapter->collection('pages.public');

        foreach ($pages as $page) {
            $pageObject = $page;
            $page = json_decode($page->getContents(), true);

            if (isset($page['route']) && is_string($page['route'])) {
                // The route is a string, so we need to translate.
                $page['route'] = [
                    $defaultLocale => $page['route']
                ];
            }

            $pagesCollection->deleteMany([
                'definition.route.' . $defaultLocale => $page['route'][$defaultLocale]
            ]);
            $pagesPublicCollection->deleteMany([
                'definition.route.' . $defaultLocale => $page['route'][$defaultLocale]
            ]);

            $lot = $lotsCollection->insertOne([
                'teams' => [$team['_id']]
            ]);

            $thumbnail = '';
            $thumbnail_path = sprintf('%s/%s.png', $pageObject->getRealPath(), $pageObject->getFileName());

            if (file_exists($thumbnail_path)) {
                $thumbnail = 'data:image/png;base64,' . base64_encode(file_get_contents($thumbnail_path));
            }

            $newPage = [
                'revision' => [
                    'hash' => md5(json_encode($page)),
                    'thumbnail' => [
                        'en-GB' => $thumbnail
                    ],
                    'lot' => $lot->getInsertedId()->__toString(),
                    'date' => gmdate('c'),
                    'user' => []
                ],
                'definition' => $page
            ];

            $this->adapter->collection('pages')->insertOne($newPage);

            // I have to sanitize the page for public here.
            $newPage['definition'] = \Frontender\Core\Controllers\Pages::sanitize($newPage['definition']);

            $this->adapter->collection('pages.public')->insertOne($newPage);
        }

        return true;
    }

	public static function importViaComposer() {
		defined('ROOT_PATH') || define('ROOT_PATH', getcwd());
		require_once getcwd() . '/vendor/autoload.php';

		$path = getcwd() . '/project/db/pages';
		// Check if folder exists
		if(!file_exists($path)) {
			return 0;
		}

		$instance = new Pages();
		$instance->import('pages', $path);

		return 0;
	}
}
