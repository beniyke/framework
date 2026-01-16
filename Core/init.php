<?php

declare(strict_types=1);

/**
 * Initializes the application, including autoloading,
 * error handling, and timezone configuration.
 *
 * @author BenIyke <beniyke34@gmail.com> | (twitter:@BigBeniyke)
 */
const MIN_PHP_VERSION = '8.2';
$interface = php_sapi_name();
$dir = __DIR__;

if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
    $message = 'PHP ' . MIN_PHP_VERSION . ' or higher is required.';
    if ($interface === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
    } else {
        http_response_code(503);
        include $dir . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Templates' . DIRECTORY_SEPARATOR . 'version.html';
    }
    exit;
}

if ($interface !== 'cli') {
    ini_set('max_execution_time', 5000);
}

umask(0000);

$autoloader_file = $dir . DIRECTORY_SEPARATOR . 'Autoload' . DIRECTORY_SEPARATOR . 'autoloader.php';
$autoloader_dir_discovery_file = $dir . DIRECTORY_SEPARATOR . 'Autoload' . DIRECTORY_SEPARATOR . 'DirectoryDiscovery.php';

if (!file_exists($autoloader_file)) {
    throw new RuntimeException("Missing essential file: $autoloader_file");
}
if (!file_exists($autoloader_dir_discovery_file)) {
    throw new RuntimeException("Missing essential file: $autoloader_dir_discovery_file");
}

require_once $autoloader_file;
require_once $autoloader_dir_discovery_file;

$basePath = rtrim(realpath('.') . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR);
$autoloaderDirectoryDiscovery = new DirectoryDiscovery($basePath);

$autoloader = new Autoloader($autoloaderDirectoryDiscovery);
$autoloader->init();

use Core\Error\ConfigurationException;
use Core\Ioc\Container;
use Core\Kernel;

$container = Container::getInstance();
$app = new Kernel($container, $basePath);

try {
    $app->boot();
} catch (ConfigurationException $e) {
    http_response_code(500);
    echo 'Fatal Configuration Error: ' . $e->getMessage();
    exit(1);
}

$container = $app->getContainer();

if ($interface === 'cli' && ob_get_level() > 0) {
    ob_end_clean();
}
