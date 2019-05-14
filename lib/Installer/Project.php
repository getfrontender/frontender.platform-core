<?php
/*******************************************************
 * @copyright 2017-2019 Dipity B.V., The Netherlands
 * @package Frontender
 * @subpackage Frontender Platform Core
 *
 * Frontender is a web application development platform consisting of a
 * desktop application (Frontender Desktop) and a web application which
 * consists of a client component (Frontender Platform) and a core
 * component (Frontender Platform Core).
 *
 * Frontender Desktop, Frontender Platform and Frontender Platform Core
 * may not be copied and/or distributed without the express
 * permission of Dipity B.V.
 *******************************************************/

namespace Frontender\Core\Installer;

use Composer\Script\Event;
use Symfony\Component\Finder\Finder;

class Project extends Base
{
    public static function import(Event $event)
    {
        $args = $event->getArguments();

        // Requiring autoloader and .env file.
        require_once getcwd() . '/vendor/autoload.php';
        $env = new \Dotenv\Dotenv(getcwd());
        $env->load();

        if (!count($args)) {
            self::writeLn('No source specified!', 'red');
            exit;
        }

        $source = $args[0];
        $tempFile = self::getTempPath('project_file');
        $tempDir = self::getTempPath('project_install', true);

        self::writeLn('Downloading project', 'blue');
        file_put_contents($tempFile, file_get_contents($source));
        $zip = new \ZipArchive();
        if ($zip->open($tempFile)) {
            $zip->extractTo($tempDir);
            $zip->close();
        } else {
            self::writeLn('Something went wrong when installing the project, please contact the project developer', 'red');
            exit;
        }

        self::writeLn('Project downloaded, installing', 'blue');
        self::importMongoFiles($tempDir);
        self::importPublicFiles($tempDir);
        self::importProjectFiles($tempDir);
        self::importLibFiles($tempDir);

        self::writeLn('Project has been imported!', 'green');
    }

    private static function importPublicFiles(string $dir): bool
    {
        $publicDir = self::findPathByDirName('public', $dir);
        $finder = new Finder();

        if (!$publicDir) {
            return false;
        }

        // Get all the files of the public directory, and move them.
        $files = $finder->files()->in($publicDir);
        foreach ($files as $file) {
            $newFilePath = getcwd() . '/public/' . $file->getRelativePath();

            @mkdir($newFilePath, 0777, true);
            @rename($file->getRealPath(), $newFilePath . '/' . $file->getFileName());
        }

        return true;
    }

    private static function importProjectFiles(string $dir): bool
    {
        $projectDir = self::findPathByDirName('project', $dir);
        $finder = new Finder();

        if (!$projectDir) {
            return false;
        }

        $directories = $finder->directories()->in($projectDir)->notName('db')->depth(0);
        foreach ($directories as $directory) {
            $filesFinder = new Finder();
            $files = $filesFinder->files()->in($directory->getRealPath());

            foreach ($files as $file) {
                $newFilePath = getcwd() . '/project/' . $directory->getFileName() . '/' .  $file->getRelativePath();

                @mkdir($newFilePath, 0777, true);
                @rename($file->getRealPath(), $newFilePath . '/' . $file->getFileName());
            }
        }

        return true;
    }

    public static function importLibFiles(string $dir): bool
    {
        $libDir = self::findPathByDirName('lib', $dir);
        $finder = new Finder();

        if (!$libDir) {
            return false;
        }

        $directories = $finder->directories()->in($libDir)->notName('db')->depth(0);
        foreach ($directories as $directory) {
            $filesFinder = new Finder();
            $files = $filesFinder->files()->in($directory->getRealPath());

            foreach ($files as $file) {
                $newFilePath = getcwd() . '/lib/' . $directory->getFileName() . '/' .  $file->getRelativePath();

                @mkdir($newFilePath, 0777, true);
                @rename($file->getRealPath(), $newFilePath . '/' . $file->getFileName());
            }
        }

        return true;
    }
}
