<?php

namespace Sitecake;

use Cache\Adapter\Filesystem\FilesystemCachePool;

class Cache
{
    /**
     * @var FilesystemCachePool
     */
    protected $cachePool;

    public function __construct(FilesystemCachePool $cachePool)
    {
        $this->cachePool = $cachePool;
    }

    /**
     * @param array $initialData
     *
     * @internal Used only inside Site class to copy existing cache if any
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function initCache(array $initialData = [])
    {
        foreach ($initialData as $key => $value) {
            $item = $this->cachePool->getItem($key);
            if (!$item->isHit()) {
                $item->set($value);
                $this->cachePool->save($item);
            }
        }
    }

    /**
     * Returns metadata stored under passed key. Default value if passed.
     *
     * @param string     $key
     * @param null|mixed $default
     *
     * @return mixed|null
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function get($key, $default = null)
    {
        $item = $this->cachePool->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        return $default;
    }

    /**
     * Returns whether there is stored metadata value under passed key
     *
     * @param string $key
     *
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function has($key)
    {
        return $this->cachePool->hasItem($key);
    }

    /**
     * Writes passed metadata under passed key
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return bool Operation success
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function save($key, $value)
    {
        $item = $this->cachePool->getItem($key);

        $item->set($value);

        return $this->cachePool->save($item);
    }

    //<editor-fold desc="Metadata methods">

    /**
     * Saves lastPublished metadata value. Called after publish event finishes
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function saveLastPublished()
    {
        $this->save('lastPublished', time());
    }

    /**
     * Marks specific source file path as dirty
     *
     * @param string $path
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function markPathDirty($path)
    {
        $unpublished = $this->get('unpublished', []);

        if (array_search($path, $unpublished) === false) {
            $unpublished[] = $path;
            $this->save('unpublished', $unpublished);
        }
    }

    /**
     * Returns list of paths that needs to be published
     *
     * @return array|bool|mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getUnpublishedPaths()
    {
        return $this->get('unpublished', []);
    }
    //</editor-fold>
}
