<?php
namespace Sitecake\Filesystem;

use League\Flysystem\Plugin\AbstractPlugin;

/**
 * Class ListPatternPaths
 *
 * @method array ensureDir(string $directory, string $pattern, bool $recursive)
 *
 * @package Sitecake\Filesystem
 */
class ListPatternPaths extends AbstractPlugin
{
    public function getMethod()
    {
        return 'listPatternPaths';
    }

    /**
     * Lists filesystem paths that match the given pattern.
     *
     * @param  string $directory directory to list
     * @param  string $pattern regexp patterns to match paths
     * @param  bool $recursive should the path listing is recursive
     *
     * @return array
     */
    public function handle($directory, $pattern, $recursive = false)
    {
        $existingFiles = $this->filesystem->listContents($directory, $recursive);
        $matchedPaths = [];
        foreach ($existingFiles as $file) {
            $normalizedPath = str_replace('\\', '/', $file['path']);
            if (preg_match($pattern, $normalizedPath) === 1) {
                $matchedPaths[] = $normalizedPath;
            }
        }

        return $matchedPaths;
    }
}
