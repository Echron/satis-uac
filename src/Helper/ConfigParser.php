<?php
declare(strict_types=1);

namespace Echron\Satis\UAC\Helper;

use Echron\Satis\UAC\Application;
use Echron\Satis\UAC\Model\EndPoint;
use Echron\Satis\UAC\Model\EndPointUser;

class ConfigParser
{

    public static function parse(
        Application $application,
        string      $appPath,
        string      $configFileName = 'config.json'
    ): void
    {
        $configFilePath = $appPath . \DIRECTORY_SEPARATOR . $configFileName;
        if (!\file_exists($configFilePath)) {
            throw new \Exception('Unable to parse configuration: file does not exist');
        }

        $config = self::getConfigFileContent($configFilePath);

        $endPointsConfig = $config['endpoints'];

        foreach ($endPointsConfig as $endPointConfig) {
            $endpoint = new EndPoint($endPointConfig['name'], $appPath . DIRECTORY_SEPARATOR . $endPointConfig['config']);
            self::appendUsersFromConfig($endPointConfig, $endpoint);
            self::appendPackagesFromConfig($endPointConfig, $endpoint);
            $application->addEndpoint($endpoint);
        }
    }

    private static function appendUsersFromConfig(array $endPointConfig, EndPoint $endPoint): void
    {
        $endPointsUsersConfig = $endPointConfig['users'];
        foreach ($endPointsUsersConfig as $endPointUserConfig) {
            $endPoint->addUser(new EndPointUser($endPointUserConfig['user'], $endPointUserConfig['key']));
        }
    }

    private static function appendPackagesFromConfig(array $endPointConfig, EndPoint $endPoint): void
    {
        $endPointPackages = $endPointConfig['packages'];
        foreach ($endPointPackages as $endPointPackage) {
            $endPoint->addPackage($endPointPackage);
        }
    }

    private static function getConfigFileContent(string $configFilePath): array
    {
        $rawConfig = file_get_contents($configFilePath);
        //TODO: handle when unable to decode content
        $config = json_decode($rawConfig, true, 512, JSON_THROW_ON_ERROR);

        return $config;
    }

}
