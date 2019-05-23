<?php

namespace Sitecake\ServiceProviders;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem as Flysystem;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Sitecake\Filesystem\Filesystem;

class FilesystemServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        $app['filesystem'] = function ($app) {
            return new Filesystem($app['filesystem.adapter']);
        };

        if (!isset($app['filesystem.adapter'])
            || !($app['filesystem.adapter'] instanceof AdapterInterface)
        ) {
            // Check if adapter is one of common adapters
            if (isset($app['filesystem.adapter']) && is_string($app['filesystem.adapter'])) {
                if (isset($app['filesystem.adapter.config'])) {
                    if (!(is_array($app['filesystem.adapter.config'])
                        && $app['filesystem.adapter.config'] === array_values($app['filesystem.adapter.config']))
                    ) {
                        $app['filesystem.adapter.config'] = [$app['filesystem.adapter.config']];
                    }
                }
                if (class_exists(
                    'League\\Flysystem\\Adapter\\' . ucfirst($app['filesystem.adapter'])
                )) {
                    $app['filesystem.adapter.class'] =
                        'League\\Flysystem\\Adapter\\' . ucfirst($app['filesystem.adapter']);
                } else {
                    $app['filesystem.adapter.class'] = $app['filesystem.adapter'];
                }
            }

            $app['filesystem.adapter'] = function ($app) {
                return new $app['filesystem.adapter.class'](...(array)$app['filesystem.adapter.config']);
            };
        }

        // Use local adapter by default
        $app['filesystem.adapter.class'] = 'League\\Flysystem\\Adapter\\Local';
        $app['filesystem.adapter.config'] = ['.'];
    }
}
