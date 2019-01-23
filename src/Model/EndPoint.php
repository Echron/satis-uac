<?php
declare(strict_types=1);

namespace Echron\Satis\UAC\Model;

class EndPoint
{
    public $name;
    public $configFile;
    private $users;
    private $packages;

    public function __construct(string $name, string $configFile)
    {
        if (empty($name)) {
            throw new \Exception('Invalid endpoint name');
        }
        if (!file_exists($configFile) || !is_readable($configFile)) {
            throw new \Exception('Invalid endpoint config file');
        }

        $this->name = $name;
        $this->configFile = $configFile;
        $this->users = [];
        $this->packages = [];
    }

    public function addUser(EndPointUser $user)
    {
        $this->users[] = $user;
    }

    /**
     * @return EndPointUser[]
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    public function addPackage(string $package, string $version = '*')
    {
        $this->packages[] = $package;//[$package] = $version;
    }

    /**
     * @return string[]
     */
    public function getPackages(): array
    {
        return $this->packages;
    }

}
