<?php

namespace Sitecake\ServiceProviders;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Sitecake\Filesystem\Filesystem;

class LocalCacheServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        $app['local_cache'] = function ($app) {
            if (!isset($app['filesystem']) || !($app['filesystem'] instanceof Filesystem)) {
                throw new \RuntimeException(
                    'You must define \'filesystem\' parameter to use the CacheServiceProvider'
                );
            }

            $cachePool = new FilesystemCachePool($app['filesystem'], $app['local_cache.dir']);

            if (isset($app['logger'])) {
                $cachePool->setLogger($app['logger']);
            }

            return $cachePool;
        };

        $app['local_cache.dir'] = 'cache';
    }
}
