<?php

namespace Sitecake\Content\DOM;

interface ElementMatcherInterface
{
    /**
     * Returns regular expression to match specific element within HTML source.
     * Can accept optional parameter to build pattern for element based on identifier
     *
     * @param string $identifier
     *
     * @return  string
     */
    public static function getOpenTagPattern($identifier = '');

    /**
     * Returns element tag name based on regular expression returned from getOpenTagPattern method
     *
     * @param array $matches
     *
     * @return mixed
     */
    public static function matchTagName($matches);

    /**
     * Returns whether element should be searched with closing tag or not
     *
     * @return bool
     */
    public static function isEmptyElement();
}
