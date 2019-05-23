<?php

namespace Sitecake\Filesystem;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\PluginInterface;
use Sitecake\Exception\UnexistingMethodException;

/**
 * Class Filesystem
 *
 * Wrapper for League's Flysystem filesystem abstraction that attaches internal required plugins
 *
 * PLUGIN METHODS
 * @method bool|string ensureDir($directory)
 * @method array listPatternPaths($directory, $pattern, $recursive = false)
 * @method bool|string randomDir($directory)
 * @method void copyPaths($paths, $source, $destination, $callback = null)
 * @method void deletePaths($paths)
 * @method string fullPath($path)
 *
 * @package Sitecake\Filesystem
 */
class Filesystem extends Flysystem
{
    protected $pluginClasses = [
        'Sitecake\\Filesystem\\EnsureDirectory',
        'Sitecake\\Filesystem\\ListPatternPaths',
        'Sitecake\\Filesystem\\RandomDirectory',
        'Sitecake\\Filesystem\\DeletePaths',
        'Sitecake\\Filesystem\\FullPath',
        'League\\Flysystem\\Plugin\\ListWith',
    ];

    protected $plugins = [];

    /**
     * Filesystem constructor.
     *
     * @param AdapterInterface $adapter
     * @param Config|array $config
     */
    public function __construct(AdapterInterface $adapter, $config = null)
    {
        parent::__construct($adapter, $config);
        // Add application specific filesystem plugins
        foreach ($this->pluginClasses as $pluginClass) {
            /** @var PluginInterface $plugin */
            $plugin = new $pluginClass();
            $this->plugins[$plugin->getMethod()] = $plugin;
            $this->addPlugin($plugin);
        }
    }

    /**
     * Delegates calls to filesystem handler
     *
     * @param string $method Method to call
     * @param array $arguments Arguments to pass to calling method
     *
     * @return mixed
     */
    public function __call($method, array $arguments)
    {
        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], $arguments);
        }

        foreach ($this->plugins as $methodName => $plugin) {
            if ($method === $methodName) {
                return parent::__call($method, $arguments);
            }
        }

        throw new UnexistingMethodException([
            'method' => $method,
            'class' => '\Sitecake\Filesystem'
        ]);
    }
}
