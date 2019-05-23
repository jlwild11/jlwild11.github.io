<?php

namespace Sitecake\Content\DOM\Query\Selector;

use Sitecake\Content\DOM\Query\SelectorInterface;
use Sitecake\Content\DOM\Query\SelectorMatcherAwareTrait;
use Sitecake\Sitecake;

class ResourceImageSelector implements SelectorInterface
{
    use SelectorMatcherAwareTrait;

    /**
     * Name of directory where images are being stored
     * This is used when matching, because each src attribute wil point to image inside this directory.
     *
     * @var string
     */
    protected $imgDirName;

    /**
     * Allowed extensions for uploaded images.
     * When matching, only these extensions will be included in regex
     *
     * @var array
     */
    protected $validExtensions;

    /**
     * Holds regex patter for image name
     *
     * @var string
     */
    protected $sourcePattern;

    /**
     * ResourceImageSelector constructor.
     */
    public function __construct()
    {
        $this->imgDirName = Sitecake::getConfig('image.directory_name');
        $this->validExtensions = Sitecake::getConfig('image.valid_extensions');
        $this->sourcePattern = '[^\s"\',]*(?:' . $this->imgDirName .
            ')\/[^\s]*\-sc[0-9a-f]{13}[^\.]*\.(' .
            implode('|', $this->validExtensions) . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function getMatcher()
    {
        return $this->attributeSelectorMatcher(
            'src',
            $this->sourcePattern,
            'img',
            '~'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFilter($filter = null, $params = [])
    {
        return null;
    }

    /**
     * Returns image source pattern prepared for preg_* functions
     *
     * @return string
     */
    public function getSourcePattern()
    {
        return '/' . $this->sourcePattern . '/';
    }
}
