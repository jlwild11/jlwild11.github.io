<?php

namespace Sitecake\ServiceProviders\Session;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\BootableProviderInterface;
use Silex\Application;

class MemcacheExtension implements ServiceProviderInterface, BootableProviderInterface
{
    public function boot(Application $app)
    {
    }

    public function register(Container $app)
    {
        $app['memcache'] = function () use ($app) {
            $library = isset($app['memcache.library']) ? strtolower($app['memcache.library']) : 'memcached';
            $servers = isset($app['memcache.server']) ? $app['memcache.server'] : [
                ['127.0.0.1', 11211]
            ];

            if ($library == 'memcache') {
                $memcache = new \Memcache();
            } elseif ($library == 'memcached') {
                $memcache = new \Memcached(serialize($servers));
            } else {
                throw new \Exception("Unsupported library '{$library}, choose between 'Memcache' or 'Memcached'");
            }
            if (($library == 'memcached' && !count($memcache->getServerList())) || $library == 'memcache') {
                foreach ($servers as $config) {
                    call_user_func_array([$memcache, 'addServer'], array_values($config));
                }
            }

            return $memcache;
        };
    }
}
