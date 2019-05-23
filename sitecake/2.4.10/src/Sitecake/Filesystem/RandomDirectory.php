<?php
namespace Sitecake\Filesystem;

use League\Flysystem\Plugin\AbstractPlugin;

/**
 * Class RandomDirectory
 *
 * @method bool|string randomDir(string $directory)
 *
 * @package Sitecake\Filesystem
 */
class RandomDirectory extends AbstractPlugin
{
    public function getMethod()
    {
        return 'randomDir';
    }

    /**
     * Returns an existing or a newly created random directory in the given directory. The
     * random directory is a directory with a random name of the certain pattern. The directory name
     * pattern used is /r[0-9a-f]{13}/.
     *
     * @param  string $directory directory that the random directory should be in
     *
     * @return bool|string returns the random directory path if operation succeeded, false otherwise
     */
    public function handle($directory)
    {
        $existingPaths = $this->filesystem->listPatternPaths($directory, '/^.*\/r[0-9a-f]{13}$/');
        if (count($existingPaths) > 0) {
            return $existingPaths[0];
        } else {
            $directory = $directory . '/' . uniqid('r');

            return $this->filesystem->ensureDir($directory) ? $directory : false;
        }
    }
}
