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

    /** @var EndPoint[] */
    private array $endPoints = [];

    private string $path;
    private string $serveFolderName;

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

    /**
     * @param string|string[]|null $repositoriesToUpdate
     * @return void
     * @throws \Exception
     */
    public function run(string|array|null $repositoriesToUpdate = null): void
    {
        if ($repositoriesToUpdate !== null) {
            if (is_array($repositoriesToUpdate)) {

                $onlyString = array_filter($repositoriesToUpdate, 'is_string');
                if (!$onlyString) {
                    throw new \InvalidArgumentException('Invalid update repositories: can only be string');
                }
            } else {
                $repositoriesToUpdate = [$repositoriesToUpdate];
            }
        }


        foreach ($this->endPoints as $endPoint) {
            try {
                $this->generateEndpoint($endPoint, $repositoriesToUpdate);
            } catch (\Throwable $ex) {
                if ($this->logger) {
                    $this->logger->error('Unable to generate endpoint "' . $endPoint->name . '" (' . $ex->getMessage() . ')', ['ex' => $ex]);
                }
            }

        }
        $this->generateTopLevelHtaccess($this->endPoints);
    }

    protected function purgeEndpoint(EndPoint $endPoint, bool $dryRun = false): void
    {
        $configFile = $endPoint->configFile;
        $arguments = [
            'command'    => 'purge',
            'file'       => $configFile,
            'output-dir' => $this->getDirectory($endPoint),


        ];

        if ($dryRun) {
            $arguments['dry-run'] = true;
        }
        if ($this->logger) {
            $this->logger->info('Purge endpoint "' . $endPoint->name . '"');

        }
        $isValid = $endPoint->validate();
        $input = new ArrayInput($arguments);

        $application = new SatisConsoleApplication();
        $application->setCatchExceptions(false);
        $application->setAutoExit(false);
        $application->run($input);
    }

    protected function generateEndpoint(EndPoint $endPoint, array|null $repositoryFilters = null): void
    {
        $skipEndPointGeneration = false;


        $configFile = $endPoint->configFile;
        $arguments = [
            'command'    => 'build',
            'file'       => $configFile,
            'output-dir' => $this->getDirectory($endPoint),

            '--skip-errors' => true,

        ];
        if ($repositoryFilters !== null && count($repositoryFilters) > 0) {

            foreach ($repositoryFilters as $repositoryFilter) {
                if (!$endPoint->hasPackageByUrl($repositoryFilter)) {
                    if ($this->logger) {
                        $this->logger->info('Skip generation of endpoint "' . $endPoint->name . '" (repository "' . $repositoryFilter . '" not activated/found)');
                    }
                    return;
                }
            }


            $arguments['--repository-url'] = $repositoryFilters;
        } else {
            if (count($endPoint->getPackages()) === 0) {
                throw new \Exception('No packages defined');
            }
            if ($endPoint->getPackages() !== ['*']) {
                $arguments['packages'] = $endPoint->getPackages();
            }
        }


        if (!$skipEndPointGeneration) {

            if ($this->logger) {
                $extra = \is_null($repositoryFilters) ? '(all repositories)' : '(single repository: ' . implode(', ', $repositoryFilters) . ')';
                $this->logger->info('Generate endpoint "' . $endPoint->name . '" ' . $extra);

            }
            $isValid = $endPoint->validate();
            $input = new ArrayInput($arguments);

            $application = new SatisConsoleApplication();
            $application->setCatchExceptions(false);
            $application->setAutoExit(false);
            $application->run($input);

            $this->generateHtaccess($endPoint);
        }
    }

    public function purge(bool $dryRun = true): void
    {

        foreach ($this->endPoints as $endPoint) {
            try {
                $this->purgeEndpoint($endPoint, $dryRun);
            } catch (\Throwable $ex) {
                if ($this->logger) {
                    $this->logger->error('Unable to generate endpoint "' . $endPoint->name . '" (' . $ex->getMessage() . ')', ['ex' => $ex]);
                }
            }

        }
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
        $htaccess .= PHP_EOL;
        $htaccess .= 'RewriteEngine On' . PHP_EOL;
        $htaccess .= PHP_EOL;

        foreach ($endPoints as $endPoint) {
            $users = $endPoint->getUsers();
            /** @var EndPointUser $user */
            foreach ($users as $user) {
                $htaccess .= 'RewriteCond %{LA-U:REMOTE_USER} ' . $user->username . PHP_EOL;
                $htaccess .= 'RewriteCond %{REQUEST_URI} !^/' . $this->sanitizeFilename($endPoint->name) . '/.*' . PHP_EOL;
                $htaccess .= 'RewriteRule ^(.*)$ /' . $this->sanitizeFilename($endPoint->name) . '/$1  [R,L]' . PHP_EOL;
                $htaccess .= PHP_EOL;
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
            if ($user->password === '' && strtolower($user->username) === 'public') {
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
