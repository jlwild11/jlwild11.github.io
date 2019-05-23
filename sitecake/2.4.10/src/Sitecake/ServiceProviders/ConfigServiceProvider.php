<?php

namespace Sitecake\ServiceProviders;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Sitecake\ServiceProviders\Config\ConfigLoaderRegistry;
use Sitecake\ServiceProviders\Config\Configuration;

class ConfigServiceProvider implements ServiceProviderInterface
{
    private $replacements = [];

    public function __construct(array $replacements = [])
    {
        if ($replacements) {
            foreach ($replacements as $key => $value) {
                $this->replacements['%'.$key.'%'] = $value;
            }
        }
    }

    public function register(Container $app)
    {
        // Check for config file. If it doesn't exist copy default configuration into source dir root
        $app['configuration'] = function ($app) {
            $configuration = new Configuration($app['configuration.registry']);

            if ($app['configuration.autoload'] &&
                $app['configuration.default_loader'] !== null &&
                !empty($app['configuration.source'])
            ) {
                $app['configuration.registry']->registerLoader($app['configuration.default_loader']);
                $configuration->load($app['configuration.source']);
            }

            return $configuration;
        };

        $app['configuration.registry'] = function ($app) {
            return new ConfigLoaderRegistry($app);
        };

        $app['configuration.autoload'] = false;
        $app['configuration.default_loader'] = null;
        $app['configuration.source'] = '';

        $app->extend('configuration', function ($configuration) {
            /* @var \Sitecake\ServiceProviders\Config\Configuration $configuration */
            $configuration->registerMutator(function ($value) {
                return $this->doReplacements($value);
            });

            return $configuration;
        });
    }

    private function doReplacements($value)
    {
        if (!$this->replacements) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->doReplacements($v);
            }

            return $value;
        }

        if (is_string($value)) {
            return strtr($value, $this->replacements);
        }

        return $value;
    }
}
