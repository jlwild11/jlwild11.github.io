<?php

namespace Sitecake\Content\DOM\Query;

interface SelectorParserInterface
{
    /**
     * Returns whether SelectorParser instance can handle passed selector string or not
     *
     * @param string $selector
     *
     * @return bool
     */
    public function match($selector);

    /**
     * Returns regular expression which will be used to match HTML content for  group of elements
     * based on passed selector string
     *
     * @param string $selector
     *
     * @return mixed
     */
    public function parse($selector);

    public function parseFilter($selector);

    public function stripFilter($selector);
}
