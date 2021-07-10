<?php
declare(strict_types=1);
require 'vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

use Echron\Satis\UAC\Application;
use Echron\Satis\UAC\Helper\ConfigParser;

$output = [];
$return_var = 0;

$path = dirname(__FILE__);

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
 * Initialize endpoints for application based on configuration file (see config.json)
 */
ConfigParser::parse($application, $path, 'config.json');

/**
 * Generate all endpoints with all packages
 */
$application->run();

/**
 * Generate based on the package url (only the endpoint for which the package is enabled will be generated)
 */
$application->run('git@bitbucket.org:attlaz/adapter-magento2-php.git');




