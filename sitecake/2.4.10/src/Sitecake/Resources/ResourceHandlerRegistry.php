<?php

namespace Sitecake\Resources;

use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventListenerInterface;
use Sitecake\Resources\Exception\MissingResourceHandlerException;
use Sitecake\Util\ObjectRegistry;

/**
 * Class ResourceHandlerRegistry
 *
 * @method ResourceHandlerInterface read($name)
 *
 * @package Sitecake\Resources
 */
class ResourceHandlerRegistry extends ObjectRegistry implements EventDispatcherInterface
{
    use EventDispatcherTrait;

    /**
     * @var ResourceManager
     */
    protected $manager;

    /**
     * ResourceHandlerRegistry constructor.
     *
     * @param ResourceManager $manager
     */
    public function __construct(ResourceManager $manager)
    {
        $this->manager = $manager;
        $this->setEventManager($this->manager->getEventManager());
    }

    /**
     * Returns resource manager
     *
     * @return ResourceManager
     */
    public function getResourceManager()
    {
        return $this->manager;
    }

    /**
     * {@inheritdoc}
     */
    protected function create($class, $alias, $config)
    {
        return new $class($this->manager, $config);
    }

    /**
     * {@inheritdoc}
     */
    protected function throwMissingClassError($class)
    {
        throw new MissingResourceHandlerException(['handler' => $class]);
    }

    /**
     * {@inheritdoc}
     */
    protected function resolveClassName($class)
    {
        return $class;
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias($class, $type = '')
    {
        return call_user_func([$class, 'type']);
    }

    /**
     * {@inheritdoc}
     */
    public function load($objectName, $config = [])
    {
        return parent::load($objectName, $config);
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
     * @return \Sitecake\Resources\ResourceHandlerInterface
     */
    public function get($name)
    {
        return parent::get($name);
    }

    /**
     * {@inheritdoc}
     */
    public function set($objectName, $object)
    {
        if ($this instanceof EventDispatcherInterface && $object instanceof EventListenerInterface) {
            $this->getEventManager()->on($object);
        }
        $this->loaded[$objectName] = $object;
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        return parent::has($name);
    }
}
