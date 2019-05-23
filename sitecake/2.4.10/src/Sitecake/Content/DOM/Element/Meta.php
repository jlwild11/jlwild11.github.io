<?php

namespace Sitecake\Content\DOM\Element;

use Sitecake\Content\DOM\Query\SelectorMatcherAwareTrait;
use Sitecake\Content\Element;

class Meta extends Element
{
    use SelectorMatcherAwareTrait;

    protected $content;

    /**
     * Meta element constructor.
     *
     * @param null|string $html
     * @param null|string $content
     */
    public function __construct($html, $content = null)
    {
        if (preg_match($this->tagSelectorMatcher('meta'), $html)) {
            parent::__construct($html);
        } elseif ($content !== null) {
            parent::__construct('<meta name="' . $html . '" content="' . $content . '">');
        } else {
            parent::__construct('<meta name="' . $html . '">');
        }

        $this->content = $this->getAttribute('content');
    }
}
