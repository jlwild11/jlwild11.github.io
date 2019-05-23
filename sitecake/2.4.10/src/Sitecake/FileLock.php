<?php

namespace Sitecake;

use Sitecake\Filesystem\Filesystem;

class FileLock
{
    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * Path to tmp dir where lock file is stored
     * @var string
     */
    protected $tmpDirPath;

    /**
     * FileLock constructor.
     *
     * @param Filesystem $fs
     * @param string $tmpDirPath
     */
    public function __construct(Filesystem $fs, $tmpDirPath)
    {
        $this->fs = $fs;
        $this->tmpDirPath = $tmpDirPath;
    }

    public function set($name, $timeout = 0)
    {
        $t = ($timeout == 0) ? 0 : (string)($this->__timestamp() + $timeout);

        $this->fs->put($this->__path($name), $t);
    }

    private function __timestamp()
    {
        return round(microtime(true) * 1000);
    }

    private function __path($name)
    {
        return $this->tmpDirPath . '/' . $name . '.lock';
    }

    /**
     * Deletes lock file
     *
     * @param $name
     *
     * @return bool
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function remove($name)
    {
        $path = $this->__path($name);

        if ($this->fs->has($path)) {
            return $this->fs->delete($path);
        }

        return true;
    }

    /**
     * Returns whether lock file exists
     *
     * @param string $name
     *
     * @return bool
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function exists($name)
    {
        $file = $this->__path($name);

        if ($this->fs->has($file)) {
            if ($this->__timedOut($file)) {
                $this->fs->delete($file);

                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * @param $lock
     *
     * @return bool
     * @throws \League\Flysystem\FileNotFoundException
     */
    private function __timedOut($lock)
    {
        $timeout = (double)$this->fs->read($lock);

        return $timeout == 0 ? false : ($timeout - $this->__timestamp()) < 0;
    }
}
