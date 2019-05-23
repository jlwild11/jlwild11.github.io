<?php

namespace Sitecake\Content\DOM\Element;

use Sitecake\Content\DOM\Query\SelectorMatcherAwareTrait;
use Sitecake\Content\Element;

class Title extends Element
{
    use SelectorMatcherAwareTrait;

    /**
     * Title text
     *
     * @var string
     */
    protected $title;

    /**
     * Meta constructor.
     *
     * @param null|string $html
     * @param array $config
     */
    public function __construct($html, $config = [])
    {
        if (preg_match($this->tagSelectorMatcher('meta'), $html)) {
            parent::__construct($html);
        } else {
            parent::__construct('<title>' . $html . '</title>');
        }

        $this->title = $this->text();
    }
}
