<?php

namespace Sitecake\Util;

use BadMethodCallException;
use LogicException;

/**
 * A trait that provides a set of static methods to manage configuration
 * for classes that need to have sets of configuration data registered and manipulated.
 */
trait StaticConfigTrait
{

    /**
     * Configuration sets.
     *
     * @var array
     */
    protected static $config = [];

    /**
     * This method can be used to define configuration adapters for an application.
     *
     * To change an adapter's configuration at runtime, first drop the adapter and then
     * reconfigure it.
     *
     * Adapters will not be constructed until the first operation is done.
     *
     * ### Usage
     *
     * Assuming that the class' name is `Cache` the following scenarios
     * are supported:
     *
     * Setting a cache engine up.
     *
     * ```
     * Cache::setConfig('default', $settings);
     * ```
     *
     * Injecting a constructed adapter in:
     *
     * ```
     * Cache::setConfig('default', $instance);
     * ```
     *
     * Configure multiple adapters at once:
     *
     * ```
     * Cache::setConfig($arrayOfConfig);
     * ```
     *
     * @param string|array $key    The name of the configuration, or an array of multiple configs.
     * @param mixed        $config An array of name => configuration data for adapter.
     *
     * @throws \BadMethodCallException When trying to modify an existing config.
     * @throws \LogicException When trying to store an invalid structured config array.
     * @return void
     */
    public static function setConfig($key, $config = null)
    {
        if ($config === null) {
            if (!is_array($key)) {
                throw new LogicException('If config is null, key must be an array.');
            }
            foreach ($key as $name => $settings) {
                static::setConfig($name, $settings);
            }

            return;
        }

        if (isset(static::$config[$key])) {
            throw new BadMethodCallException(sprintf('Cannot reconfigure existing key "%s"', $key));
        }

        static::$config[$key] = $config;
    }

    /**
     * Reads existing configuration.
     *
     * @param string $key The name of the configuration.
     *
     * @return array|null|string Array of configuration data or configuration value.
     */
    public static function getConfig($key)
    {
        return isset(static::$config[$key]) ? static::$config[$key] : null;
    }

    /**
     * Drops a constructed adapter.
     *
     * If you wish to modify an existing configuration, you should drop it,
     * change configuration and then re-add it.
     *
     * If the implementing objects supports a `$_registry` object the named configuration
     * will also be unloaded from the registry.
     *
     * @param string $config An existing configuration you wish to remove.
     *
     * @return bool Success of the removal, returns false when the config does not exist.
     */
    protected static function drop($config)
    {
        if (!isset(static::$config[$config])) {
            return false;
        }
        unset(static::$config[$config]);

        return true;
    }

    /**
     * Returns an array containing the named configurations
     *
     * @return array Array of configurations.
     */
    public static function configured()
    {
        return array_keys(static::$config);
    }
}
