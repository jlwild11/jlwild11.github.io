<?php

namespace Sitecake\Content;

trait ContentAwareTrait
{
    /**
     * HTML source
     *
     * @var string
     */
    protected $content;

    /**
     * Loads passed HTML source
     *
     * @param string $html
     */
    public function setContent($html)
    {
        $this->content = trim($html);
    }

    /**
     * Returns HTML source
     *
     * @return string
     */
    public function getContent()
    {
        return trim($this->content);
    }
}
