<?php

namespace Sitecake\Resources\Handler;

use Sitecake\Resources\AbstractResourceHandler;

class UploadHandler extends AbstractResourceHandler
{
    protected $defaultConfig = [
        'forbiddenExtensions' => ['php', 'php5', 'php4', 'php3', 'phtml', 'phpt'],
        'uploadsDirName' => 'files'
    ];

    /**
     * {@inheritdoc}
     */
    public static function type()
    {
        return 'upload';
    }

    /**
     * {@inheritdoc}
     */
    public function getPathMatcher()
    {
        $extensionsPattern = implode('|', array_map(function ($extensions) {
            return preg_quote($extensions, '/') . '$';
        }, (array)$this->getConfig('forbiddenExtensions')));
        return '^' . $this->getConfig('uploadsDirName') .
            '\/.*\-sc[0-9a-f]{13}[^\.]*\.(?!' . $extensionsPattern . ').+$';
    }

    /**
     * Returns path for directory needed by handler
     *
     * @param string $paths
     *
     * @return string
     */
    public function requiredPaths($paths)
    {
        return $this->getConfig('uploadsDirName');
    }
}
