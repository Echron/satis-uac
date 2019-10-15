<?php
declare(strict_types=1);

namespace Echron\Satis\UAC;

use Composer\Satis\Console\Application as SatisConsoleApplication;
use Echron\Satis\UAC\Model\EndPoint;
use Echron\Satis\UAC\Model\EndPointUser;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Input\ArrayInput;

class Application implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $endPoints;

    private $path;
    private $serveFolderName;

    public function __construct(string $path, string $serveFolderName = 'pub')
    {
        $this->path = $path;
        $this->serveFolderName = $serveFolderName;
    }

    public function addEndpoint(EndPoint $endPoint): void
    {
        //TODO: check if not already exist
        $this->endPoints[$endPoint->name] = $endPoint;
    }

    public function run(string $updateRepository = null): void
    {
        /**
         * @var string $key
         * @var EndPoint $endPoint
         */
        foreach ($this->endPoints as $endPoint) {
            if ($this->logger) {
                $this->logger->info('Generate "' . $endPoint->name . '"');
            }
            $configFile = $endPoint->configFile;
            $arguments = [
                'command'    => 'build',
                'file'       => $configFile,
                'output-dir' => $this->getDirectory($endPoint),

                '--skip-errors' => true,

            ];
            if (!\is_null($updateRepository)) {
                //TODO: should we validate the givenpackages?

                $packages = $endPoint->getPackages();

                foreach ($packages as $package) {
                    //                    var_dump($package);
                    //                    die('---');
                }
                $arguments['--repository-url'] = $updateRepository;
            } else {
                if (count($endPoint->getPackages()) === 0) {
                    throw new \Exception('No packages defined');
                }
                if ($endPoint->getPackages() !== ['*']) {
                    $arguments['packages'] = $endPoint->getPackages();
                }
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

    protected function getDirectory(EndPoint $endPoint): string
    {
        return $this->path . \DIRECTORY_SEPARATOR . $this->serveFolderName . \DIRECTORY_SEPARATOR . $this->sanitizeFilename($endPoint->name);
    }

    protected function generateHtaccess(EndPoint $endPoint): void
    {
        $htaccessPath = $this->getDirectory($endPoint) . DIRECTORY_SEPARATOR . '.htaccess';

        $htpasswdPath = $this->path . DIRECTORY_SEPARATOR . '.htpasswd.' . $this->sanitizeFilename($endPoint->name);

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
    protected function generateTopLevelHtaccess(array $endPoints): void
    {
        $htaccessPath = $this->path . DIRECTORY_SEPARATOR . $this->serveFolderName . DIRECTORY_SEPARATOR . '.htaccess';
        $htpasswdPath = $this->path . DIRECTORY_SEPARATOR . '.htpasswd.global';

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
                $htaccess .= 'RewriteCond %{REQUEST_URI} !^/' . $this->sanitizeFilename($endPoint->name) . '/.*' . PHP_EOL;
                $htaccess .= 'RewriteRule ^(.*)$ /' . $this->sanitizeFilename($endPoint->name) . '/$1  [R,L]' . PHP_EOL;
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

    protected function encryptPassword(string $password): string
    {
        return crypt($password, base64_encode($password));
    }

    protected function isPublic(EndPoint $endPoint): bool
    {
        /** @var EndPointUser $user */
        foreach ($endPoint->getUsers() as $user) {
            if (strtolower($user->username) === 'public' && $user->password === '') {
                return true;
            }
        }

        return false;
    }

    private function sanitizeFilename(string $filename): string
    {
        return preg_replace("/[^a-z0-9\.]/", "", strtolower($filename));
    }
}
