<?php

namespace Frontender\Core\Installer;

use MongoDB\Client;
use Frontender\Core\DB\Adapter;
use Composer\Script\Event;
use Frontender\Core\Utils\Manager;

defined('ROOT_PATH') || define('ROOT_PATH', getcwd());

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
            'MONGO_DB' => $installData['mongo_dbname'],
            'FEP_TOKEN_SECRET' => $installData['token']
        ]);

        if (!$success) {
            self::writeLn('Errors have occured, please consult the console for details!', 'red');
            return 0;
        } else {
            self::writeLn('.env file is created.', 'blue');
        }

        if (!self::importSiteSettings($installData)) {
            self::writeLn('Site data could not be imported, do you have the right token?', 'red');
            return 0;
        } else {
            self::writeLn('Site data is imported.', 'blue');
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

        if (!isset($data['token'])) {
            $success = false;
            self::writeLn('No installation token found, please add this token.', 'red');
            self::writeLn('This token is given after the site is registred.', 'red');
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
                'ENV' => 'production',
                'FEP_TOKEN_HEADER' => 'X-Token'
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

    protected static function importSiteSettings($installData): bool
    {
        // We now know that the token exists.
        try {
            $response = Manager::getInstance()->post('sites/import', [
                'json' => [
                    'import_token' => bin2hex(base64_encode($installData['token']))
                ]
            ]);
            $contents = json_decode($response->getBody()->getContents());
            $adapter = Adapter::getInstance();

            if ($contents->status !== 'success') {
                return false;
            }

            // Create a new site team.
            $adapter->collection('teams')->drop();
            $adapter->collection('teams')->insertOne([
                'name' => 'Site',
                'users' => array_map(function ($id) {
                    return (int)$id;
                }, $contents->data->administrators)
            ]);

            $adapter->collection('settings')->drop();
            $adapter->collection('settings')->insertOne([
                'site_id' => $contents->data->site_id
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        } catch (\Error $e) {
            return false;
        }
    }
}
