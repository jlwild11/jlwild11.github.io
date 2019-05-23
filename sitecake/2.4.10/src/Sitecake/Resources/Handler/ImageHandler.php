<?php

namespace Sitecake\Resources\Handler;

use Sitecake\Resources\AbstractResourceHandler;

class ImageHandler extends AbstractResourceHandler
{
    protected $defaultConfig = [
        'validExtensions' => ['jpg', 'jpeg', 'png', 'gif'],
        'imageDirName' => 'images'
    ];

    /**
     * {@inheritdoc}
     */
    public static function type()
    {
        return 'image';
    }

    /**
     * {@inheritdoc}
     */
    public function getPathMatcher()
    {
        $extensionsPattern = implode('|', array_map(function ($extensions) {
            return preg_quote($extensions, '/') . '$';
        }, (array)$this->getConfig('validExtensions')));
        return '^' . $this->getConfig('imageDirName') . '\/.*\-sc[0-9a-f]{13}[^\.]*\.(' . $extensionsPattern . ')';
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
        return $this->getConfig('imageDirName');
    }
}
