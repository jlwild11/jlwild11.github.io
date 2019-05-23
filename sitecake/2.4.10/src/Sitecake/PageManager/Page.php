<?php

namespace Sitecake\PageManager;

use Sitecake\Content\ContentManager;
use Sitecake\Content\ContentManagerAwareTrait;
use Sitecake\Content\Element;
use Sitecake\Resources\SourceFile;
use Sitecake\Sitecake;
use Sitecake\Util\Beautifier;
use Sitecake\Util\HtmlUtils;
use Sitecake\Util\Utils;

class Page
{
    use ContentManagerAwareTrait;

    /**
     * Page identifier
     *
     * @var mixed
     */
    protected $id;

    /**
     * Page source file
     *
     * @var SourceFile
     */
    protected $source;

    /**
     * Page scripts
     *
     * @var array
     */
    protected $scripts = [];

    /**
     * Page styles
     *
     * @var array
     */
    protected $styles = [];

    /**
     * Page HEAD
     *
     * @var Element
     */
    protected $head;

    /**
     * Page BODY
     *
     * @var Element
     */
    protected $body;

    /**
     * Draft constructor.
     *
     * @param TemplateInterface $source
     * @param mixed $id
     */
    public function __construct($source, $id = null)
    {
        // Set page ID
        if (!empty($id)) {
            $this->id = $id;
        } else {
            $this->id = Utils::id();
        }
        $this->source = $source;
        $this->contentManager = new ContentManager();
        $this->contentManager->setContent($this->source->evaluateSource());
        $this->head = $this->contentManager->query('head', 'first');
        $this->body = $this->contentManager->query('body', 'first');
    }

    /**
     * @return mixed|null|string
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Renders evaluated page
     *
     * @param bool $beautify Indicates whether output should be indented or not
     *
     * @return string
     * @throws \Exception
     */
    public function render($beautify = false)
    {
        foreach ($this->scripts as $script) {
            if (!$script['loaded']) {
                $attributes = [];
                if (!empty($script['attributes'])) {
                    $attributes = $script['attributes'];
                    unset($script['attributes']);
                }
                $this->addScript($script['source'], $script, $attributes);
            }
        }

        foreach ($this->styles as $style) {
            if (!$style['loaded']) {
                $this->addStyle($style['source'], $style);
            }
        }

        if ($beautify) {
            return Beautifier::indent($this->contentManager->getContent(), Sitecake::getConfig('content.indent'));
        }

        return $this->contentManager->getContent();
    }


    /**
     * Checks whether passed container is inside editable container
     *
     * @param Element $element
     *
     * @return bool
     */
    public function isEditableElement(Element $element)
    {
        $containers = $this->contentManager->listContentContainers();
        foreach ($containers as $container) {
            if ($this->contentManager->isChildOf($element, $container)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds script to page source.
     *  'position' option determines whether script will be added to head or before closing body tag.
     *  'inline' option determines whether passed script is code or script URL
     *
     * @param string $source  Script URL or js code
     * @param array  $options Options
     * @param array  $attributes Attributes. If $options parameter is not passed this will be considered as $options
     *
     * @return $this
     */
    protected function addScript($source, $options = [], $attributes = [])
    {
        $options += ['position' => 'top', 'inline' => false];

        if ($options['inline']) {
            $source = HtmlUtils::inlineScript($source, $attributes);
        } else {
            $source = HtmlUtils::script($source, $attributes);
        }
        $element = new Element($source);
        if ($options['position'] !== 'top') {
            $this->appendTo($this->body, $element);
        } else {
            $this->appendTo($this->head, $element);
        }

        $this->scripts[] = [
            'source' => $source,
            'loaded' => true
        ] + $options;

        return $this;
    }

    /**
     * Adds passed javascript to scripts queue
     *
     * @param string $source Script URL or js code
     * @param array $options Options
     * @param array  $attributes Attributes. If $options parameter is not passed this will be considered as $options
     *
     * @return $this
     */
    public function enqueueScript($source, $options = [], $attributes = [])
    {
        $options += ['position' => 'top', 'inline' => false];

        $this->scripts[] = [
            'source' => $source,
            'loaded' => false,
            'attributes' => $attributes
        ] + $options;

        return $this;
    }

    /**
     * Adds link css script script to page head
     *
     * @param string $source CSS file source
     * @param array $options Options
     *
     * @return $this
     */
    protected function addStyle($source, $options = [])
    {
        $options += ['inline' => false];

        if ($options['inline']) {
            $source = HtmlUtils::inlineStyle($source);
        } else {
            $source = HtmlUtils::css($source);
        }

        $element = new Element($source);
        $this->appendTo($this->head, $element);

        $this->styles[] = [
                'source' => $source,
                'loaded' => true
            ] + $options;

        return $this;
    }

    /**
     * Adds passed style/css code to styles queue
     *
     * @param string $source
     * @param array $options
     *
     * @return $this
     */
    public function enqueueStyle($source, $options = [])
    {
        $this->styles[] = [
            'source' => $source,
            'loaded' => false
        ] + $options;

        return $this;
    }
}
