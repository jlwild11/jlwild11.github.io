<?php

namespace Sitecake\ServiceProviders\Config;

use Pimple\Container;
use Sitecake\ServiceProviders\File\Exception\MissingConfigLoaderException;
use Sitecake\Util\ObjectRegistry;

/**
 * Class ConfigLoaderRegistry
 *
 * @method ConfigLoaderInterface read($name)
 *
 * @package Sitecake\ServiceProviders\Config
 */
class ConfigLoaderRegistry extends ObjectRegistry
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * ConfigLoaderRegistry constructor.
     *
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->container = $app;
    }

    /**
     * Register configuration loader under passed name
     *
     * @param string $name
     * @param array  $config
     *
     * @return ConfigLoaderInterface
     * @throws \Exception
     */
    public function registerLoader($name, array $config = [])
    {
        if (!isset($config['className'])) {
            $config['className'] = $name;
            $name = $this->getAlias($name, 'Loader');
        }

        return $this->load($name, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function loaded()
    {
        return parent::loaded();
    }

    /**
     * {@inheritdoc}
     */
    protected function resolveClassName($class)
    {
        if (class_exists($class) && is_subclass_of($class, ConfigLoaderInterface::class)) {
            return $class;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @throws MissingConfigLoaderException
     */
    protected function throwMissingClassError($class)
    {
        throw new MissingConfigLoaderException([
            'loader' => $class
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @return ConfigLoaderInterface
     */
    protected function create($class, $alias, $config)
    {
        /* @var ConfigLoaderInterface $instance */
        return new $class($this, $config);
    }
}
