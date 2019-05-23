<?php

namespace Sitecake\Api;

use Cake\Event\EventManager;
use Pimple\Container;
use Sitecake\Cache;
use Sitecake\PageManager\TemplateManagerInterface;

class Sitecake
{
    /**
     * Sitecake instance
     *
     * @var Container
     */
    protected $app;

    /**
     * Sitecake configuration
     *
     * @var array
     */
    protected $defaultConfig = [];

    /**
     * Sitecake API constructor.
     *
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Returns event bus instance
     *
     * @return EventManager
     */
    public function eventBus()
    {
        return $this->app['event_bus'];
    }

    /**
     * Returns cache manager instance
     *
     * @return Cache
     */
    public function cache()
    {
        return $this->app['sc.cache'];
    }

    public function getFilesystem()
    {
        return $this->app['filesystem'];
    }

    /**
     * @return TemplateManagerInterface
     */
    public function getTemplateManager()
    {
        return $this->app['sc.template_manager'];
    }

    /**
     * Register service under passed name with passed configuration
     *
     * @param string $name
     * @param array  $config
     *
     * @return mixed
     */
    public function registerService($name, array $config = [])
    {
        return $this->app['sc.service_registry']->register($name, $config);
    }
}
