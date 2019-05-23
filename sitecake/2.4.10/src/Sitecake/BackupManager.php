<?php

namespace Sitecake;

use League\Flysystem\Exception;
use Sitecake\Filesystem\Filesystem;
use Sitecake\Resources\ResourceManager;

class BackupManager
{
    const SITECAKE_BKP_DIR_NAME = 'sitecake-backup';

    const DEFAULT_NUMBER_OF_BACKUPS = 2;

    /**
     * Filesystem instance
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Resource manager
     *
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * Path to backup directory
     *
     * @var string
     */
    protected $backupPath;

    /**
     * Stores paths for which backup failed
     * @var array
     */
    protected $failedPaths = [];

    /**
     * BackupManager constructor.
     *
     * @param Filesystem $fs
     * @param ResourceManager $resourceManager
     */
    public function __construct(Filesystem $fs, ResourceManager $resourceManager)
    {
        $this->fs = $fs;
        $this->resourceManager = $resourceManager;

        $this->ensureDirectoryStructure();

        $this->resourceManager->ignore(self::SITECAKE_BKP_DIR_NAME . '/');
    }

    /**
     * Creates 'sitecake-backup' for storing backups on Sitecake.beforePublish event
     */
    protected function ensureDirectoryStructure()
    {
        // check/create sitecake-backup
        try {
            if (!$this->fs->ensureDir(self::SITECAKE_BKP_DIR_NAME)) {
                throw new \LogicException(
                    'Could not ensure that the directory /' . self::SITECAKE_BKP_DIR_NAME . ' is present and writable.'
                );
            }
        } catch (\RuntimeException $e) {
            throw new \LogicException(
                'Could not ensure that the directory /' . self::SITECAKE_BKP_DIR_NAME . ' is present and writable.'
            );
        }

        // check/create sitecake-backup/<random-working-dir>
        try {
            $backupPath = $this->fs->randomDir(self::SITECAKE_BKP_DIR_NAME);
            if ($backupPath === false) {
                throw new \LogicException(
                    'Could not ensure that the work directory in /' .
                    self::SITECAKE_BKP_DIR_NAME . ' is present and writable.'
                );
            }
            $this->backupPath = $backupPath;
        } catch (\RuntimeException $e) {
            throw new \LogicException(
                'Could not ensure that the work directory in /' .
                self::SITECAKE_BKP_DIR_NAME . ' is present and writable.'
            );
        }
    }

    /**
     * Creates backup of current public files handled by sitecake
     */
    public function backup()
    {
        $paths = $this->resourceManager->listResources();
        if (Sitecake::getConfig('site.number_of_backups', self::DEFAULT_NUMBER_OF_BACKUPS) < 1) {
            return;
        }
        // Create backup dir
        $backupPath = $this->newBackupContainerPath();
        $this->fs->createDir($backupPath);
        // Create resource dirs
        $this->fs->createDir($backupPath . '/images');
        $this->fs->createDir($backupPath . '/files');
        // Copy files
        foreach ($paths as $path) {
            // New files will only exists in draft dir, but would be added to metadata
            if ($this->fs->has($path)) {
                $newPath = $backupPath . '/' . $path;
                try {
                    if (!$this->fs->has($newPath)) {
                        $this->fs->copy($path, $newPath);
                    } else {
                        $this->fs->update($newPath, $this->fs->read($path));
                    }
                } catch (Exception $e) {
                    $this->failedPaths[] = $path;
                }
            }
        }
        // Remove expired backup files
        $this->cleanupBackup();
    }

    /**
     * Generates backup path based on current datetime
     *
     * @return string
     */
    protected function newBackupContainerPath()
    {
        $path = $this->getBackupPath() . '/' . date('Y-m-d-H.i.s') . '-'
            . substr(uniqid(), -2);

        return $path;
    }

    /**
     * Returns the path of the backup directory.
     *
     * @return string the backup dir path
     */
    public function getBackupPath()
    {
        return $this->backupPath;
    }

    /**
     * Remove all backups except for the last recent five.
     */
    protected function cleanupBackup()
    {
        $backups = $this->fs->listContents($this->getBackupPath());
        usort($backups, function ($a, $b) {
            if ($a['timestamp'] < $b['timestamp']) {
                return -1;
            } elseif ($a['timestamp'] == $b['timestamp']) {
                return 0;
            } else {
                return 1;
            }
        });
        $backups = array_reverse($backups);
        foreach ($backups as $idx => $backup) {
            if ($idx >= Sitecake::getConfig('site.number_of_backups', self::DEFAULT_NUMBER_OF_BACKUPS)) {
                $this->fs->deleteDir($backup['path']);
            }
        }
    }
}
