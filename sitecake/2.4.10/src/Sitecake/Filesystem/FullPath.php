<?php

namespace Sitecake\Filesystem;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Plugin\AbstractPlugin;

/**
 * Class DeletePaths
 *
 * @property \League\Flysystem\Filesystem $filesystem
 * @method void fullPath(array $paths)
 *
 * @package Sitecake\Filesystem
 */
class FullPath extends AbstractPlugin
{
    public function getMethod()
    {
        return 'fullPath';
    }

    /**
     * Returns full path for passed relative path. If adapter doesn't implement applyPathPrefix method
     * passed path will be returned
     *
     * @param  string $path A list of paths to be deleted.
     *
     * @return string
     */
    public function handle($path)
    {
        /* @var AbstractAdapter $adapter */
        $adapter = $this->filesystem->getAdapter();
        if (method_exists($adapter, 'applyPathPrefix')) {
            return $adapter->applyPathPrefix($path);
        }

        return $path;
    }
}
