<?php

namespace Sitecake\Plugin;

use Composer\Autoload\ClassLoader;
use Sitecake\Api\Exception\MissingPluginException;
use Sitecake\Api\Sitecake;
use Sitecake\ServiceProviders\Config\Configuration;
use Sitecake\Util\ObjectRegistry;
use Sitecake\Util\Text;

class PluginRegistry extends ObjectRegistry
{
    const NAMESPACE_PREFIX = 'Sitecake\\Plugins\\';

    /**
     * Absolute path to plugin directory
     *
     * @var string
     */
    protected $baseDir;

    /**
     * Class loader
     *
     * @var ClassLoader
     */
    protected $classLoader;

    protected $configuration;

    protected $api;

    /**
     * PluginRegistry constructor.
     *
     * @param string        $pluginPath    Absolute path to directory where plugins are stored
     * @param ClassLoader   $classLoader   Class loader
     * @param Configuration $configuration Configuration handler
     * @param Sitecake      $api           API provider
     */
    public function __construct($pluginPath, $classLoader, $configuration, $api)
    {
        $this->baseDir = $pluginPath;
        $this->classLoader = $classLoader;
        $this->configuration = $configuration;
        $this->api = $api;
    }

    /**
     * Adds passed plugin instance directly to loaded plugins
     *
     * @param string $name
     * @param object $pluginInstance
     */
    public function add($name, $pluginInstance)
    {
        $this->loaded[$name] = $pluginInstance;
    }

    /**
     * {@inheritdoc}
     */
    protected function resolveClassName($class)
    {
        $className = Text::camelize($class, ['_', '-']);
        $namespace = self::NAMESPACE_PREFIX . $className . '\\';
        $this->classLoader->setPsr4($namespace, [
            $this->baseDir . DIRECTORY_SEPARATOR . $class,
            $this->baseDir . DIRECTORY_SEPARATOR . $class . DIRECTORY_SEPARATOR . 'src',
            $this->baseDir . DIRECTORY_SEPARATOR . $class . DIRECTORY_SEPARATOR . 'lib'
        ]);

        return $namespace . $className;
    }

    /**
     * {@inheritdoc}
     */
    protected function throwMissingClassError($class)
    {
        $className = Text::camelize($class, ['_', '-']);
        $namespace = self::NAMESPACE_PREFIX . $className . '\\';
        throw new MissingPluginException([
            'plugin' => $namespace . $className
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function create($class, $alias, $config)
    {
        return new $class($this->api, $config);
    }

    public function loadConfiguration($plugin)
    {
        $path = $this->baseDir . DIRECTORY_SEPARATOR . $plugin . DIRECTORY_SEPARATOR . 'config.php';
        $config = $this->configuration->consume($path, $plugin);
    }
}
