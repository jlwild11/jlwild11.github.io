<?php

namespace Sitecake\Api;

use Sitecake\Cache;
use Sitecake\Resources\ResourceManager as Manager;

/**
 * Class ResourceManager
 *
 * API wrapper for Sitecake\Resources\ResourceManager class
 *
 * @package Sitecake\Api
 */
class ResourceManager
{
    /**
     * @var Manager
     */
    protected $manager;

    /**
     * @var Cache
     */
    protected $metadata;

    public function __construct(Manager $manager, Cache $metadata)
    {
        $this->manager = $manager;
        $this->metadata = $metadata;
    }

    /**
     * Wrapper class for Sitecake\ResourceManager::getDraftPath method.
     * Returns draft path for resource if passed.
     * Without arguments, method returns the path of the draft directory.
     *
     * @param null|string $resource
     * @param bool $full
     *
     * @return string
     */
    public function getDraftPath($resource = null, $full = false)
    {
        return $this->manager->getDraftPath($resource, $full);
    }

    /**
     * Returns Metadata instance
     *
     * @return Cache
     */
    public function getMetadata()
    {
        return $this->metadata;
    }
}
