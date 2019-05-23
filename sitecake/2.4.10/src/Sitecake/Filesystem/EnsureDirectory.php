<?php
namespace Sitecake\Filesystem;

use League\Flysystem\Plugin\AbstractPlugin;

/**
 * Class EnsureDirectory
 *
 * @method bool|string ensureDir(string $directory)
 *
 * @package Sitecake\Filesystem
 */
class EnsureDirectory extends AbstractPlugin
{
    public function getMethod()
    {
        return 'ensureDir';
    }

    /**
     * Ensures that the specified directory exists by creating it
     * if not exists already.
     *
     * @param  string $directory directory path
     *
     * @return bool|string returns the path of the directory if operation succeeded, false otherwise
     */
    public function handle($directory)
    {
        if ($this->filesystem->has($directory)) {
            return $this->filesystem->get($directory)->isDir() ? $directory : false;
        } else {
            return $this->filesystem->createDir($directory) ? $directory : false;
        }
    }
}
