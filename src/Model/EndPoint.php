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

    public function addUser(EndPointUser $user): void
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

    public function addPackage(string $package, string $version = '*'): void
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

    public function validate(): bool
    {
        // Check if repositories are correctly defined
        $packageRepositories = $this->getConfigRepositories();
        foreach ($packageRepositories as $packageRepository) {
            if (!isset($packageRepository['name'])) {
                throw new \Exception('Repository "' . $packageRepository['url'] . '" has no name');
                return false;
            }
        }


        // Check if packages are defined
        if (count($this->packages) === 0) {
            throw new \Exception('No packages defined');
            return false;
        }
        $packages = $this->getPackages();
        if ($packages === ['*']) {
            return true;
        }
        // Validate that enabled packages have a corresponding repository
        foreach ($packages as $package) {
            $packageRepository = $this->getPackageRepositoryByName($package);
            if (\is_null($packageRepository)) {
                throw new \Exception('Package "' . $package . '" not found in repositories');
                return false;
            }
        }

        return true;
    }

    public function hasPackageByUrl(string $packageUrl): bool
    {
        $packageRepository = $this->getPackageRepositoryByUrl($packageUrl);
        if (\is_null($packageRepository)) {
//            $available = [];
//            foreach ($this->getConfigRepositories() as $configRepository) {
//                $available[] = $configRepository['url'];
//            }
//            echo 'repo "' . $packageRepository . '" not found, available: ' . \implode(', ', $available);
            return false;
        }

        $packages = $this->getPackages();
        if ($packages === ['*']) {
            return true;
        }

        foreach ($packages as $package) {
            if (isset($packageRepository['name']) && $packageRepository['name'] === $package) {
                return true;
            }
        }
        return false;
    }

    public function getPackageRepositoryByUrl(string $packageUrl): ?array
    {
        $configRepositories = $this->getConfigRepositories();
        foreach ($configRepositories as $configRepository) {
            if (isset($configRepository['url']) && \strtolower($configRepository['url']) === \strtolower($packageUrl)) {
                return $configRepository;
            }
        }

        return null;
    }

    public function getPackageRepositoryByName(string $packageName): ?array
    {
        $configRepositories = $this->getConfigRepositories();
        foreach ($configRepositories as $configRepository) {
            if (isset($configRepository['name']) && $configRepository['name'] === $packageName) {
                return $configRepository;
            }
        }

        return null;
    }


    private $configFileContent = null;

    private function getConfigRepositories(): array
    {
        $configFileContent = $this->getConfigFileContent();
        return $configFileContent['repositories'];
    }

    private function getConfigFileContent(): array
    {
        if (\is_null($this->configFileContent)) {

            $rawConfigFileContent = file_get_contents($this->configFile);
            if ($rawConfigFileContent === false) {
                throw new \Error('Unable to read config file content');
            }
            $configFileContent = json_decode($rawConfigFileContent, true);
            if ($configFileContent === null) {
                throw new \Error('Unable to read config file content');
            }
            $this->configFileContent = $configFileContent;
        }
        return $this->configFileContent;
    }

}
