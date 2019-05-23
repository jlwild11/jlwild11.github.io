<?php
namespace Sitecake\Filesystem;

use League\Flysystem\Plugin\AbstractPlugin;

/**
 * Class DeletePaths
 *
 * @method void deletePaths(array $paths)
 *
 * @package Sitecake\Filesystem
 */
class DeletePaths extends AbstractPlugin
{
    public function getMethod()
    {
        return 'deletePaths';
    }

    /**
     * Deletes the given list of file paths.
     *
     * @param  array $paths a list of paths to be deleted.
     *
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function handle($paths)
    {
        foreach ($paths as $path) {
            $this->filesystem->delete($path);
        }
    }
}
