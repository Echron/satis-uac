<?php
declare(strict_types=1);
require 'vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

use Echron\Satis\UAC\Application;
use Echron\Satis\UAC\Helper\ConfigParser;

$output = [];
$return_var = 0;

$path = __DIR__;

$application = new Application($path, 'pub');

/**
 * We recommend using a logger to get notified about issues
 */
$logger = new \Monolog\Logger('Satis');
$logger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::DEBUG));

$application->setLogger($logger);

/**
 * Public endpoint
 */
//$publicEndPoint = new EndPoint('', 'satis.packages.json');
//$publicEndPoint->addUser(new EndPointUser('public', ''));
//$endPoints[] = $publicEndPoint;
/**
 * Private endpoints
 */

//Company A has access to all packages defined in satis.packages.json
//$companyAEndpoint = new EndPoint('companyA', $path . DIRECTORY_SEPARATOR . 'satis.packages.json');
//$companyAEndpoint->addUser(new EndPointUser('user1key', 'user1password'));
//$companyAEndpoint->addPackage('*');
//
//$application->addEndpoint($companyAEndpoint);
//
////Company B has only access to 2 packages
//$companyBEndpoint = new EndPoint('companyB', $path . DIRECTORY_SEPARATOR . 'satis.packages.json');
//$companyBEndpoint->addUser(new EndPointUser('user2key', 'user2password'));

//$companyBEndpoint->addPackage('vendor/package1');
//$companyBEndpoint->addPackage('vendor/package2');

//$application->addEndpoint($companyBEndpoint);
/**
 * Initialize endpoints for application based on configuration file (see example.config.json)
 */
ConfigParser::parse($application, $path, 'example.config.json');

/**
 * Generate all endpoints with all packages
 */
$application->run();

/**
 * Generate based on the package url. Only the endpoint for which the package is enabled will be generated and only those packages passed will get updated. (can be single string or array)
 */
$application->run('git@bitbucket.org:vendor/mypackage.git');

$application->run(['git@bitbucket.org:vendor/mypackage.git', 'git@bitbucket.org:vendor/mypackage2.git']);



