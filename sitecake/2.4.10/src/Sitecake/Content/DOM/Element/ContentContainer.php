<?php

namespace Sitecake\Content\DOM\Element;

use Sitecake\Content\DOM\Query\Selector\ResourceFileLinkSelector;
use Sitecake\Content\Element;

class ContentContainer extends Element
{
    /**
     * Indicates whether content container is named or not
     *
     * @var bool
     */
    protected $isNamed;

    /**
     * Content container name
     *
     * @var string
     */
    protected $name = '';

    /**
     * Default configuration used by element
     *
     * @var array
     */
    protected $defaultConfig = [
        'baseClass' => 'sc-content'
    ];

    /**
     * ContentContainer constructor.
     *
     * {@inheritdoc}
     */
    public function __construct($html, $config = [])
    {
        parent::__construct($html, $config = []);

        $class = $this->getAttribute('class');
        $baseClass = $this->getConfig('baseClass');
        if (preg_match('/(?:^|\s)' . preg_quote($baseClass) . '(?:\-([^\s]+))/', $class, $matches) === 1) {
            $this->isNamed = !(preg_match('/_cnt_[0-9]+/', $matches[1]) === 1);
            $this->name = $matches[1];
        } else {
            $this->isNamed = false;
        }
    }

    /**
     * Returns whether container is named container
     *
     * @return bool
     */
    public function isNamed()
    {
        return $this->isNamed;
    }

    /**
     * Returns container name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Generates (or re-generates if specified) container name and adds it to class attribute
     *
     * @param bool $reGenerate Indicates whether name should be re-generated
     *
     * @return string
     * @throws \LogicException If trying to generate name on named container
     */
    public function generateName($reGenerate = false)
    {
        if ($this->isNamed) {
            throw new \LogicException('Can\'t generate name on named container.');
        }
        $baseClass = $this->getConfig('baseClass');
        if (empty($this->name) || $reGenerate) {
            if ($reGenerate) {
                $this->removeClass($baseClass . '-' . $this->name);
            }
            $this->name = '_cnt_' . mt_rand() . mt_rand();
            $this->addClass($baseClass . '-' . $this->name);
        }

        return $this->name;
    }

    /**
     * Removes generated container name
     */
    public function clearGeneratedName()
    {
        if (!$this->isNamed && !empty($this->name)) {
            $this->removeClass($this->getConfig('baseClass') . '-' . $this->name);
        }
    }

    /**
     * Returns all resource urls within element
     *
     * @return array
     */
    public function listResourceUrls()
    {
        $urls = [];
        $resourceFileLinkSelector = new ResourceFileLinkSelector();
        foreach ($this->domElement->getElementsByTagName('a') as $link) {
            /** @var \DOMElement $link */
            $href = $link->getAttribute('href');
            if (preg_match($resourceFileLinkSelector->getMatcher(), $href)) {
                $urls[] = $href;
            }
        }
        $resourceFileLinkSelector = new ResourceFileLinkSelector();
        foreach ($this->domElement->getElementsByTagName('img') as $image) {
            /** @var \DOMElement $image */
            $src = $image->getAttribute('src');
            if (preg_match($resourceFileLinkSelector->getMatcher(), $src)) {
                $urls[] = $src;
            }
            $srcSet = $image->getAttribute('srcset');
            $paths = explode(',', $srcSet);
            foreach ($paths as &$path) {
                list($src,) = explode(' ', $path);
                if (preg_match($resourceFileLinkSelector->getMatcher(), $src)) {
                    $urls[] = $src;
                }
            }
        }

        return $urls;
    }
}
