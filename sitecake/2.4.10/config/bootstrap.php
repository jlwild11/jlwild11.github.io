<?php

$loader = require __DIR__ . '/../vendor/autoload.php';

use JDesrosiers\Silex\Provider\CorsServiceProvider;
use Silex\Application;
use Silex\Provider\LocaleServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Sitecake\Error\ErrorHandler;
use Sitecake\ServiceProviders\EventBusServiceProvider;
use Sitecake\Log\Formatter\PathReplaceFormatter;
use Sitecake\ServiceProviders\ConfigServiceProvider;
use Sitecake\ServiceProviders\Config\Loader\PhpConfigLoader;
use Sitecake\ServiceProviders\FilesystemServiceProvider;
use Sitecake\ServiceProviders\LocalCacheServiceProvider;
use Symfony\Component\Translation\Loader\YamlFileLoader;

// Instantiate Silex application
$app = new Application();

// Detect environment (default: prod) by checking for the existence of SITECAKE_ENVIRONMENT constant
if (defined('SITECAKE_ENVIRONMENT') && in_array(SITECAKE_ENVIRONMENT, ['prod', 'dev', 'test'])) {
    $app['environment'] = SITECAKE_ENVIRONMENT;
} else {
    $app['environment'] = 'prod';
}

// Check requirements if not in 'test' environment
if (in_array($app['environment'], ['prod', 'dev'])) {
    require __DIR__ . '/requirements.php';
}

// Current Sitecake version
define('SITECAKE_VERSION', '2.4.10');

// Include test bootstrap
if ($app['environment'] == 'test') {
    require __DIR__ . '/../tests/bootstrap.php';
}

$app['class_loader']= function () use ($loader) {
    return $loader;
};

// Register filesystem provider for handling local files (logging, cache)
$app->register(new FilesystemServiceProvider());

// Register configuration provider/handler for loading default app configuration
$app->register(new ConfigServiceProvider());
$app->extend('configuration.registry', function ($configurationLoaderRegistry) {
    /* @var \Sitecake\ServiceProviders\Config\ConfigLoaderRegistry $configurationLoaderRegistry */
    $configurationLoaderRegistry->registerLoader(PhpConfigLoader::class);

    return $configurationLoaderRegistry;
});

$app->register(new EventBusServiceProvider());

// Register logger
if (!isset($app['log']) || $app['log'] !== false) {
    $app->register(new MonologServiceProvider(), [
        'monolog.use_error_handler' => false
    ]);
    $app->extend('monolog', function($monolog, $app) {
        unset($app['monolog.listener']);

        return $monolog;
    });
}

// Register cache provider/handler
$app->register(new LocalCacheServiceProvider());

// Initialize error handling
$app['exception_handler'] = function ($app) {
    return ErrorHandler::register(
        \Sitecake\Sitecake::getConfig('error.level'),
        $app['logger'],
        new PathReplaceFormatter([
            \Sitecake\Sitecake::getPath('SITE_ROOT') => '[SITE]',
            DIRECTORY_SEPARATOR => '/'
        ])
    )->setDebug(\Sitecake\Sitecake::getConfig('debug'));
};

// Register Translation provider
$app->register(new LocaleServiceProvider());
$app->register(new TranslationServiceProvider(), [
    'locale_fallbacks' => ['en'],
]);
$app->extend('translator', function ($translator, $app) {
    /* @var \Symfony\Component\Translation\Translator $translator */
    $translator->addLoader('yaml', new YamlFileLoader());
    $translator->addResource(
        'yaml',
        \Sitecake\Sitecake::getPath('SITECAKE_CORE') . DIRECTORY_SEPARATOR .
        'locale' . DIRECTORY_SEPARATOR . 'en.yml', 'en'
    );

    return $translator;
});

// Register cross-origin resource sharing (CORS) provider
$app->register(new CorsServiceProvider());
$app->after($app["cors"]);
