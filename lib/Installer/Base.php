<?php

namespace Frontender\Core\Installer;

use Colors\Color;
use Symfony\Component\Finder\Finder;
use Frontender\Core\Installer\Importer\Generic;

class Base
{
    protected static function writeLn(string $line): void
    {
        $args = func_get_args();
        if (count($args)) {
            $args = array_slice($args, 1);
        }

        $color = new Color();
        $line = $color($line);

        foreach ($args as $arg) {
            $line = $line->{$arg};
        }

        echo $line . PHP_EOL;
    }

    protected static function getTempPath($prefix, $isDir = false)
    {
        $tempDir = sys_get_temp_dir();
        $tempFile = tempnam($tempDir, $prefix);

        if ($isDir) {
            unlink($tempFile);
            mkdir($tempFile);
        }

        return $tempFile;
    }

    protected static function importMongoFiles($baseDir): bool
    {
        // We will provide a base directory, this will give us the initial entry for the import,
        // After that we can use glob to get the files that we want to import, this will only be JSON files!
        // The folders in the baseDir will be the collections created.
        // For some collections we will have custom importers, for the others we will just import everything as is.
        $success = true;

        try {
            $finder = new Finder();
            $directories = $finder->directories()->in($baseDir)->depth('0');

            foreach ($directories as $directory) {
                $path = $directory->getRealPath();
                $name = $directory->getBasename();
                $importer = new Generic();

                // Check if we can find a specialized importer.
                // Else we will use the generic.
                try {
                    $class = 'Frontender\\Core\\Installer\\Importer\\' . ucfirst(strtolower($name));
                    $importer = new $class();
                } catch (\Exception $e) {
                    // NOOP
                } catch (\Error $e) {
                    // NOOP
                }

                try {
                    $importer->import($name, $path);
                } catch (\Exception $e) {
                    self::writeLn($e->getMessage(), 'red');
                    $success = false;
                } catch (\Error $e) {
                    self::writeLn($e->getMessage(), 'red');
                    $success = false;
                }
            }
        } catch (\Exception $e) {
            return false;
        } catch (\Error $e) {
            return false;
        }

        return $success;
    }
}
