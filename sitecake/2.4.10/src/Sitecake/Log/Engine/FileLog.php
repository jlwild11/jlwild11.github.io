<?php

namespace Sitecake\Log\Engine;

use Sitecake\Storage\Filesystem;
use Sitecake\Util\Utils;

class FileLog extends BaseLog
{
    /**
     * Number of log archives to be kept on server
     * @var int
     */
    protected $archiveSize = 0;

    /**
     * Log files directory relative path
     * @var string
     */
    protected $path = 'sitecake-temp/logs';

    /**
     * Filename for application errors
     * @var string
     */
    protected $file = 'sitecake.log';

    /**
     * Maximum size of one log file before it is archived
     * @var mixed
     */
    protected $size;

    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * Debug log types
     * @var array
     */
    protected $debugTypes = ['notice', 'info', 'debug'];

    /**
     * Specifies current debug mode
     * @var bool
     */
    protected $debugMode = false;

    /**
     * FileLog constructor.
     *
     * @param Filesystem $fs
     * @param array      $config
     */
    public function __construct(Filesystem $fs, $config = [])
    {
        $this->fs = $fs;

        // Read debug mode from app configuration
        if (isset($config['debug']) && !empty($config['debug'])) {
            $this->debugMode = true;
        }

        // Read log file size before file is archived
        if (isset($config['log.size'])) {
            if (is_numeric($config['log.size'])) {
                $this->size = (int)$config['log.size'];
            } else {
                $this->size = Utils::parseFileSize($config['log.size']);
            }
        }

        // Read number of log archive files kept
        if (isset($config['log.archive_size'])) {
            $this->archiveSize = $config['log.archive_size'];
        }

        // Ensure log directory
        try {
            if (isset($config['log.path'])) {
                $pathParts = explode('/', $config['log.path']);
                $this->file = array_pop($pathParts);
                $this->path = implode('/', $pathParts);
            }
            if (!$this->fs->ensureDir($this->path)) {
                throw new \LogicException(
                    sprintf('Could not ensure that the directory %s is present and writable.', $this->path)
                );
            }
        } catch (\RuntimeException $e) {
            throw new \LogicException('Could not ensure that the directory /sitecake/logs is present and writable.');
        }
    }

    /**
     * Implements writing to log files.
     *
     * @param string $level The severity level of the message being written.
     *                        See Cake\Log\Log::$_levels for list of possible levels.
     * @param string $message The message you want to log.
     * @param array $context Additional information about the logged message
     *
     * @return bool success of write.
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function log($level, $message, array $context = [])
    {
        if (in_array($level, $this->debugTypes) && !$this->debugMode) {
            return true;
        }
        $message = $this->format($message, $context);

        $output = '[' . date('Y-m-d H:i:s') . ']' . ' ' . ucfirst($level) . ': ' . $message . "\n";

        $filename = $this->getFilename($level);

        $pathname = $this->path . '/' . $filename;

        // Ensure log files
        if (!$this->fs->has($pathname)) {
            $this->fs->write($pathname, '');
        }

        if (!empty($this->size)) {
            $this->archiveFile($filename);
        }

        $content = $this->fs->read($pathname);

        return (bool)$this->fs->write($pathname, $content . $output);
    }

    /**
     * Get filename based on log level
     *
     * @param string $level The level of log.
     *
     * @return string File name
     */
    protected function getFilename($level)
    {
        $filename = $this->file;

        if (in_array($level, $this->debugTypes)) {
            $filename = 'sc-debug.log';
        }

        return $filename;
    }

    /**
     * Archive log file if size specified in config is reached.
     * Also if `rotate` count is reached oldest file is removed.
     *
     * @param string $filename Log file name
     *
     * @return bool True if archived successfully or no need for archiving or false in case of error.
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     */
    protected function archiveFile($filename)
    {
        $filePath = $this->path . '/' . $filename;
        clearstatcache(true, $filePath);

        $metadata = $this->fs->getMetadata($filePath);

        if ($metadata['size'] < $this->size) {
            return true;
        }

        if ($this->archiveSize === 0) {
            $result = $this->fs->delete($filePath);
        } else {
            $result = $this->fs->rename($filePath, $filePath . '.' . time());
            $this->fs->write($filePath, '');
        }

        $files = glob($filePath . '.*');
        if ($files) {
            $filesToDelete = count($files) - $this->archiveSize;
            while ($filesToDelete > 0) {
                $this->fs->delete(array_shift($files));
                $filesToDelete--;
            }
        }

        return $result;
    }
}
