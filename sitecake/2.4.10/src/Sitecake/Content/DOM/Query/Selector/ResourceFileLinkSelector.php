<?php

namespace Sitecake\Content\DOM\Query\Selector;

use Sitecake\Content\DOM\Query\SelectorInterface;
use Sitecake\Content\DOM\Query\SelectorMatcherAwareTrait;
use Sitecake\Sitecake;

class ResourceFileLinkSelector implements SelectorInterface
{
    use SelectorMatcherAwareTrait;

    /**
     * Name of directory where uploaded files are being stored
     * This is used when matching, because each URL wil point to file inside this directory.
     *
     * @var string
     */
    protected $uploadDirName;

    /**
     * Forbidden extensions for uploaded files.
     * When matching, these extensions will be excluded in regex
     *
     * @var array
     */
    protected $forbiddenExtensions;

    /**
     * ResourceFileLinkSelector constructor.
     */
    public function __construct()
    {
        $this->uploadDirName = Sitecake::getConfig('upload.directory_name');
        $this->forbiddenExtensions = Sitecake::getConfig('upload.forbidden_extensions');
    }

    /**
     * {@inheritdoc}
     */
    public function getMatcher()
    {
        return $this->attributeSelectorMatcher(
            'href',
            '([^\s"\',]*(?:' . $this->uploadDirName . ')\/[^\s]*\-sc[0-9a-f]{13}[^\.]*' .
            '\.(?!' . implode('|', $this->forbiddenExtensions) . ')[A-Za-z0-9]+)',
            'a',
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
}
