<?php
declare(strict_types=1);

namespace Echron\Satis\UAC;

use Composer\Satis\Console\Application as SatisConsoleApplication;
use Echron\Satis\UAC\Model\EndPoint;
use Echron\Satis\UAC\Model\EndPointUser;
use Symfony\Component\Console\Input\ArrayInput;

class Application
{

    private $endPoints;
    private $serveFolder;

    public function __construct(string $serveFolder)
    {
        $this->serveFolder = $serveFolder;
    }

    public function addEndpoint(EndPoint $endPoint)
    {
        //TODO: check if not already exist
        $this->endPoints[$endPoint->name] = $endPoint;
    }

    public function run()
    {
        /**
         * @var string $key
         * @var EndPoint $endPoint
         */
        foreach ($this->endPoints as $endPoint) {
            echo 'Generate "' . $endPoint->name . '"' . PHP_EOL;
            $configFile = $endPoint->configFile;
            $arguments = [
                'command'    => 'build',
                'file'       => $configFile,
                'output-dir' => $this->getDirectory($endPoint),

                '--skip-errors' => true,

            ];

            if (count($endPoint->getPackages()) === 0) {
                throw new \Exception('No packages defined');
            }
            if ($endPoint->getPackages() !== ['*']) {
                $arguments['packages'] = $endPoint->getPackages();
            }

            $input = new ArrayInput($arguments);

            $application = new SatisConsoleApplication();
            $application->setCatchExceptions(false);
            $application->setAutoExit(false);
            $application->run($input);
            $this->generateHtaccess($endPoint);
        }
        $this->generateTopLevelHtaccess($this->endPoints);
    }

    function getDirectory(EndPoint $endPoint)
    {
        global $path;

        $pubFolder = $path . DIRECTORY_SEPARATOR . 'pub' . DIRECTORY_SEPARATOR;

        return $pubFolder . $endPoint->name;
    }

    function generateHtaccess(EndPoint $endPoint)
    {
        global $path;

        $htaccessPath = $this->getDirectory($endPoint) . DIRECTORY_SEPARATOR . '.htaccess';

        $htpasswdPath = $path . DIRECTORY_SEPARATOR . '.htpasswd.' . $endPoint->name;

        $htaccess = 'AuthType Basic' . PHP_EOL;
        $htaccess .= 'AuthName "Restricted Content"' . PHP_EOL;
        $htaccess .= 'AuthUserFile ' . $htpasswdPath . PHP_EOL;
        $htaccess .= 'Require valid-user' . PHP_EOL;

        $htpasswd = '';
        /** @var EndPoint $endPoint */

        $users = $endPoint->getUsers();
        /** @var EndPointUser $user */
        foreach ($users as $user) {
            $htpasswd .= $user->username . ':' . $this->encryptPassword($user->password) . PHP_EOL;
        }

        file_put_contents($htaccessPath, $htaccess);
        file_put_contents($htpasswdPath, $htpasswd);
    }

    /**
     * @param EndPoint[] $endPoints
     */
    function generateTopLevelHtaccess(array $endPoints)
    {
        global $path;

        $htaccessPath = $path . DIRECTORY_SEPARATOR . 'pub' . DIRECTORY_SEPARATOR . '.htaccess';
        $htpasswdPath = $path . DIRECTORY_SEPARATOR . '.htpasswd.global';

        $htaccess = 'AuthType Basic' . PHP_EOL;
        $htaccess .= 'AuthName "Restricted Content"' . PHP_EOL;
        $htaccess .= 'AuthUserFile ' . $htpasswdPath . PHP_EOL;
        $htaccess .= 'Require valid-user' . PHP_EOL;
        $htaccess .= '' . PHP_EOL;
        $htaccess .= 'RewriteEngine On' . PHP_EOL;
        $htaccess .= '' . PHP_EOL;

        foreach ($endPoints as $endPoint) {
            $users = $endPoint->getUsers();
            /** @var EndPointUser $user */
            foreach ($users as $user) {
                $htaccess .= 'RewriteCond %{LA-U:REMOTE_USER} ' . $user->username . PHP_EOL;
                $htaccess .= 'RewriteCond %{REQUEST_URI} !^/' . $endPoint->name . '/.*' . PHP_EOL;
                $htaccess .= 'RewriteRule ^(.*)$ /' . $endPoint->name . '/$1  [R,L]' . PHP_EOL;
                $htaccess .= '' . PHP_EOL;
            }
        }

        $htpasswd = '';
        /** @var EndPoint $endPoint */
        foreach ($endPoints as $endPoint) {
            $users = $endPoint->getUsers();
            /** @var EndPointUser $user */
            foreach ($users as $user) {
                $htpasswd .= $user->username . ':' . $this->encryptPassword($user->password) . PHP_EOL;
            }
        }

        file_put_contents($htaccessPath, $htaccess);
        file_put_contents($htpasswdPath, $htpasswd);
    }

    function encryptPassword(string $password): string
    {
        return crypt($password, base64_encode($password));
    }

    function isPublic(EndPoint $endPoint)
    {
        /** @var EndPointUser $user */
        foreach ($endPoint->getUsers() as $user) {
            if (strtolower($user->username) === 'public' && $user->password === '') {
                return true;
            }
        }

        return false;
    }

}
