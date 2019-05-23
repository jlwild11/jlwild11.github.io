<?php

namespace Sitecake\Services;

use Sitecake\Exception\MissingServiceException;
use Sitecake\Api\Sitecake;
use Sitecake\Site;
use Sitecake\Util\ObjectRegistry;

/**
 * Class ServiceRegistry
 *
 * @method Service read($name)
 *
 * @package Sitecake\Services
 */
class ServiceRegistry extends ObjectRegistry
{
    protected $site;

    /**
     * ServiceRegistry constructor.
     *
     * @param Site $site
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Register service under passed name
     *
     * @param string $name
     * @param array  $config
     *
     * @return ServiceInterface
     * @throws \Exception
     */
    public function register($name, array $config = [])
    {
        if (!isset($config['className'])) {
            $config['className'] = $name;
            $name = $this->getAlias($name, 'Service');
        }

        return $this->load($name, $config);
    }

    /**
     * {@inheritdoc}
     */
    protected function getAlias($class, $type = '')
    {
        return '_' . parent::getAlias($class, $type);
    }

    /**
     * Throws MissingServiceException.
     * Method is called while registering service, when service class name could not be resolved
     * {@inheritdoc}
     */
    protected function throwMissingClassError($class)
    {
        throw new MissingServiceException([
            'service' => $class
        ]);
    }

    /**
     * Resolves passed class name.
     * When registering, service name should be passed as full namespaced class name
     * so we just return passed class
     * {@inheritdoc}
     */
    protected function resolveClassName($class)
    {
        if (class_exists($class) && is_subclass_of($class, ServiceInterface::class)) {
            return $class;
        }

        return null;
    }

    /**
     * Create an instance of a given service and registers it in silex app.
     * {@inheritdoc}
     *
     * @return ServiceInterface
     */
    protected function create($class, $alias, $config)
    {
        return new $class($this->site, $alias, $config);
    }
}
