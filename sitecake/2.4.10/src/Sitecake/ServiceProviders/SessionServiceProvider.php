<?php

namespace Sitecake\ServiceProviders;

use Pimple\Container;
use Silex\Provider\SessionServiceProvider as Provider;
use Sitecake\ServiceProviders\Session\MemcacheExtension;

class SessionServiceProvider extends Provider
{
    public function register(Container $app)
    {
        parent::register($app);

        $config = $app['config']['session'];

        if (isset($config['save_handler']) && $config['save_handler'] != 'files') {
            if (is_null($config['save_handler'])) {
                $app['session.storage.handler'] = null;
            } else {
                $availableSessionHandlers = ['memcache', 'memcached', 'redis'];
                if (in_array($config['save_handler'], $availableSessionHandlers)) {
                    $app['session.storage.handler'] = function ($app) use ($config) {
                        $class = ucfirst($config['save_handler']);

                        if (!class_exists($class)) {
                            throw new \RuntimeException(sprintf(
                                'PHP does not have "%s" session module registered',
                                $config['save_handler']
                            ));
                        }

                        $sessionHandler =
                            "Symfony\\Component\\HttpFoundation\\Session\\Storage\\Handler\\{$class}SessionHandler";

                        if ($class == 'Redis') {
                            // Check if server details passed
                            $server = ['127.0.0.1', 6379]; // Default server host and port
                            if (isset($config['options']['server'])) {
                                $server = $config['options']['server'];
                            }

                            $options = [
                                'key_prefix' => 'sc'
                            ];

                            // Check if prefix passed
                            if (isset($config['options']['prefix'])) {
                                $options['key_prefix'] = $config['options']['prefix'];
                            }

                            // Check if lifetime passed
                            $sessionTimeout = 60 * 60 * 24;// Default lifetime is 1 day

                            $redis = new \Redis();
                            $redis->connect($server[0], $server[1]);

                            return new $sessionHandler($redis,
                                (isset($config['options']['expiretime']) ?
                                    $config['options']['expiretime'] : $sessionTimeout), $options);
                        } else {
                            // Check if server details passed
                            $servers = [['127.0.0.1', 11211]]; // Default server host and port
                            if (isset($config['options']['servers'])) {
                                $servers = $config['options']['servers'];
                            }

                            $options = [
                                'prefix' => 'sc',
                                'expiretime' => 60 * 60 * 24 // Default lifetime is 1 day
                            ];

                            // Check if prefix passed
                            if (isset($config['options']['prefix'])) {
                                $options['prefix'] = $config['options']['prefix'];
                            }

                            // Check if lifetime passed
                            if (isset($config['options']['expiretime'])) {
                                $options['expiretime'] = $config['options']['expiretime'];
                            }

                            $app->register(new MemcacheExtension(), [
                                'memcache.library' => $config['save_handler'],
                                'memcache.server' => $servers
                            ]);

                            return new $sessionHandler($app['memcache'], $options);
                        }
                    };
                }
            }
        } elseif (isset($config['options'])) {
            if (!empty($config['options']['save_path'])) {
                $app['session.storage.save_path'] = $config['options']['save_path'];
            } else {
                // Check if php have privileges to write into session storage path, if not set it to sitecake-temp
                $savePath = ini_get('session.save_path');
                if ((empty($savePath) || !@is_writable($savePath)) && !empty($app['session.default_save_path'])) {
                    $savePath = $app['session.default_save_path'];
                    if (DIRECTORY_SEPARATOR !== '/') {
                        $savePath = str_replace('/', DIRECTORY_SEPARATOR, $savePath);
                    }
                }
                $app['session.storage.save_path'] = $savePath;
            }

            $app['session.storage.options'] = $config['options'];
        }

        $app['session.default_save_path'] = '';
    }
}
