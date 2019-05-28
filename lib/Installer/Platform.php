<?php

namespace Frontender\Core\Installer;

use MongoDB\Client;
use Frontender\Core\DB\Adapter;
use Composer\Script\Event;

class Platform extends Base
{
    public static $core_repo_url = 'https://github.com/DipityBV/frontender.core-controls/archive/master.zip';

    public static function install(Event $event)
    {
        $currentPath = getcwd();
        $installFile = 'install.json';
        $installFilePath = $currentPath . '/' . $installFile;
        $installData = null;

        // I will call another autoload so sub-functions are also included.
        // This can be done because we have a post function.
        require_once getcwd() . '/vendor/autoload.php';

        if (self::isInstalled()) {
            self::writeLn('Your project seems to be installed already.', 'green');
            return 0;
        }

        // Check if we can find the file.
        if (!file_exists($installFilePath)) {
            self::writeLn('Install file isn\'t found, please create this one according to documentation', 'red');
            return 0;
        } else {
            $installData = json_decode(file_get_contents($installFilePath), true);
        }

        self::writeLn('Install file found, checking contents!', 'blue');

        if (!self::checkInstallFile($installData)) {
            self::writeLn('Errors have occured, please consult the console for details!', 'red');
            return 0;
        }

        self::writeLn('Install file is correct, installing all elements!', 'blue');

        // Create the .env file with the data and use it.
        $success = self::writeEnvFile([
            'MONGO_HOST' => $installData['mongo_host'],
            'MONGO_DB' => $installData['mongo_dbname']
        ]);

        if (!$success) {
            self::writeLn('Errors have occured, please consult the console for details!', 'red');
            return 0;
        } else {
            self::writeLn('.env file is created.', 'blue');
        }

        self::writeLn('Downloading core controls.', 'blue');
        $tempFile = self::getTempPath('tmp_frontender_zip');
        $tempDir = self::getTempPath('tmp_frontender_install', true);
        $adapter = Adapter::getInstance();

        // Download zip file to temp file.
        file_put_contents($tempFile, file_get_contents(self::$core_repo_url));
        self::writeLn('Core controls downloaded, installing…', 'blue');
        $zip = new \ZipArchive();

        if ($zip->open($tempFile)) {
            $zip->extractTo($tempDir);
            $zip->close();
        } else {
            self::writeLn('Something went wrong when installing the core controls, please contact a developer', 'red');
            return 0;
        }

        // Create a new site team.
        $adapter->collection('teams')->drop();
        $adapter->collection('teams')->insertOne([
            'name' => 'Site'
        ]);

        $adapter->collection('settings')->insertOne([
            'name' => $installData['project_name'] ?? null,
            'scopes' => [[
                'protocol' => 'http',
                'domain' => $installData['domain'],
                'locale' => $installData['locale'],
                'locale_prefix' => substr($installData['locale'], 0, 2)
            ]]
        ]);

        // We can now upload all the data to the database, the connection is ready etc.
        // We have to check the db directory for all the imports.
        if (!self::importMongoFiles($tempDir)) {
            self::writeLn('Errors where encountered while importing the core information, please contact a developer!', 'red');
            return 0;
        }

        self::writeLn('Everyhing is installed successfully, have fun using Frontender!', 'green');
        return 0;
    }

    protected static function checkInstallFile($data): bool
    {
        $success = true;

        if (version_compare(PHP_VERSION, '7.1.0', "<")) {
            $success = false;
            self::writeLn('PHP version must be at least 7.1.0', 'red');
        }

        /*******************************/
        /** MongoDB information check **/
        /*******************************/
        if (isset($data['mongo_host']) && isset($data['mongo_dbname'])) {
            if (empty($data['mongo_host'])) {
                $success = false;
                self::writeLn('mongo_host is empty, please check this value!', 'red');
            } else if (empty($data['mongo_dbname'])) {
                $success = false;
                self::writeLn('mongo_dbname is empty, please check this value!', 'red');
            } else {
                // Everything seems ok at first glance, not we will check the real connection.
                try {
                    new Client($data['mongo_host']);
                } catch (\Exception $e) {
                    $success = false;
                    self::writeLn('A MongoDB connection couldn\'t be made, please check your connection string.', 'red');
                }
            }
        } else {
            $success = false;
            self::writeLn('MongoDB configuration is missing information, please check.', 'red');
        }

        /*******************************/
        /** Locale information check  **/
        /*******************************/
        if (!isset($data['locale']) || empty($data['locale']) || strlen($data['locale']) !== 5 || strpos($data['locale'], '-') === false) {
            $success = false;
            self::writeLn('Locale isn\'t set according to RFC5646 notation.', 'red');
        }

        /*******************************/
        /** Domain information check  **/
        /*******************************/
        if (!isset($data['domain']) || empty($data['domain'])) {
            $success = false;
            self::writeLn('Domain isn\'t set, please set a domain on which you want to host Frontender', 'red');
        }

        return $success;
    }

    private static function isInstalled()
    {
        $root = getcwd();
        $lockFile = $root . '/composer.lock';
        $dotEnvFile = $root . '/.env';

        if (!file_exists($lockFile)) {
            return false;
        }

        return file_exists($dotEnvFile);
    }

    protected static function writeEnvFile($data, $installPath = null): bool
    {
        try {
            $dotEnvPath = ($installPath ?: getcwd()) . '/.env';
            $data = array_merge([
                'ENV' => 'development',
                'FEP_TOKEN_HEADER' => 'X-Token',
                'FEP_TOKEN_SECRET' => ''
            ], $data);

            // Create the file in the current directory, this depends on where the item is installed.
            // We can maybe get this from the event.
            $dotEnvHandle = fopen($dotEnvPath, 'w');

            foreach ($data as $key => $value) {
                fwrite($dotEnvHandle, strtoupper($key) . '="' . $value . '"' . "\r\n");

                // Load the data into the $_ENV.
                $_ENV[strtolower($key)] = $_ENV[$key] = $value;
            }

            fclose($dotEnvHandle);
        } catch (\Exception $e) {
            self::writeLn($e->getMessage(), 'red');

            return false;
        } catch (\Error $e) {
            self::writeLn($e->getMessage(), 'red');

            return false;
        }

        return true;
    }
}
