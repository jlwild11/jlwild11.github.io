<?php

namespace Sitecake\Resources;

use Sitecake\Filesystem\Filesystem;
use Sitecake\Site;
use Sitecake\Util\InstanceConfigTrait;

abstract class AbstractResourceHandler implements ResourceHandlerInterface
{
    use InstanceConfigTrait;

    /**
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * AbstractResourceHandler constructor.
     *
     * @param ResourceManager $resourceManager
     * @param array $config
     */
    public function __construct(ResourceManager $resourceManager, $config = [])
    {
        $this->resourceManager = $resourceManager;
        $this->setConfig($config);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($path)
    {
        return (bool)preg_match('/' . $this->getPathMatcher() . '/', $path);
    }

    /**
     * {@inheritdoc}
     * 
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function createDraft($path, $resource = null)
    {
        if ($resource !== null) {
            return $resource;
        }

        return $this->resourceManager->read($path);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function prepareForPublish($draftPath)
    {
        return $this->resourceManager->read($draftPath);
    }

    /**
     * {@inheritdoc}
     */
    public function normalizePath($path)
    {
        return $path;
    }

    /**
     * {@inheritdoc}
     */
    public function pathToUrl($path)
    {
        return $path;
    }

    /**
     * {@inheritdoc}
     */
    public function implementedEvents()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function requiredPaths($draftPath)
    {
        return null;
    }
}
