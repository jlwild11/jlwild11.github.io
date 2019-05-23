<?php
/**
 * @var Silex\Application $app
 */

$configPath = realpath(__DIR__ . '/../config/');
require $configPath . '/bootstrap.php';

$sc = \Sitecake\Sitecake::createInstance($app, $configPath);

$sc->initializeRequestHandling();

if ($app['environment'] == 'test') {
    return $app;
} else {
    $sc->run();
}
