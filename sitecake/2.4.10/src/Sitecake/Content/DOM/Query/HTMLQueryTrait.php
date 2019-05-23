<?php

namespace Sitecake\Content\DOM\Query;

use Sitecake\Content\ElementList;
use Sitecake\Util\Text;
use Sitecake\Util\Utils;

/**
 * Trait HTMLQueryTrait
 *
 * Provides query method to query HTML source for elements and  provides API to build selectors.
 * HTML can be queries by several type of selectors by default:
 *      + By element id (#id or tagName#id)
 *      + By class name (.className or tagName.className)
 *      + By attribute value ([attr="value" or tagName[attr="value"]])
 *        Also supports querying by attribute starting with (^), ending with ($) or containing (*) specific value
 * There is possibility to use selector filters. Default filters are :first, :last and :eq(0)
 *
 * Trait provides public method registerSelectorParser which allows to register SelectorParserInterface instance
 * that provides additional selector parsing and result filtering functionality.
 *
 * Implementing objects are expected to declare a `createElement` method which should return object of an element and
 * `$content` property which contains HTML content to be queried.
 *
 * @package Sitecake\Content\DOM\Query
 */
trait HTMLQueryTrait
{
    use SelectorMatcherAwareTrait;

    protected $voidElements = [
        'area',
        'base',
        'br',
        'col',
        'embed',
        'hr',
        'img',
        'input',
        'link',
        'meta',
        'param',
        'source',
        'track',
        'wbr'
    ];

    protected $builtInFilters = ['first', 'last', 'eq'];

    /**
     * Array of custom selectors.
     *
     * @var SelectorParserInterface[]
     */
    protected $selectorParserRegistry = [];

    /**
     * Cache of query results by query
     *
     * @var ElementList[]
     */
    protected $queryResultCache = [];

    /**
     * Queries HTML content and returns collection of elements found based on specific query
     *
     * @param string|SelectorInterface $selector
     * @param string|callable|null     $filter
     * @param mixed|null               $params [optional] Optional parameters to be passed to filter method
     *
     * @return ElementList
     */
    public function query($selector, $filter = null, $params = null)
    {
        $elementClass = null;
        $originalSelector = $selector;
        $filterCallback = null;
        $elementConfig = [];

        if (isset($params['elementClass'])) {
            $elementClass = $params['elementClass'];
            unset($params['elementClass']);
        }

        if (isset($params['elementConfig'])) {
            $elementConfig = $params['elementConfig'];
            unset($params['elementConfig']);
        }

        if ($selector instanceof SelectorInterface) {
            $originalSelector = trim($selector->getMatcher(), '/');
            $filterCallback = $selector->getFilter($filter, $params) ?: null;
        } elseif (is_callable($filter)) {
            $filterCallback = function (ElementList $elements) use ($filter, $params) {
                return $filter($elements, ...$params);
            };
        } else {
            if (($filterCallback = $this->getFilter($selector)) !== null) {
                // Remove filter from selector
                $selector = preg_replace($this->selectorFilterPattern, '', $selector);
            }
            if ($filter !== null && ($callback = $this->__getFilterCallback($filter, $params)) !== null) {
                $filterCallback = $callback;
            }
        }

        if (isset($this->queryResultCache[$originalSelector])) {
            if ($this->queryResultCache[$originalSelector] === false) {
                return new ElementList([]);
            }

            if ($filterCallback !== null) {
                return $filterCallback($this->queryResultCache[$originalSelector]);
            }

            return $this->queryResultCache[$originalSelector];
        }

        return $this->__findElements($originalSelector, $selector, $filterCallback, $elementClass, $elementConfig);
    }

    /**
     * Adds passed selectors to custom selector list
     *
     * @param SelectorParserInterface $selectorParser
     */
    public function registerSelectorParser(SelectorParserInterface $selectorParser)
    {
        $this->selectorParserRegistry[] = $selectorParser;
    }

    /**
     * Parses passed selector string and returns regular expression which will be used to match HTML content
     * for  group of elements
     *
     * @param string|SelectorInterface $selector
     *
     * @return string
     */
    protected function parseSelector($selector)
    {
        if ($selector instanceof SelectorInterface) {
            return $selector->getMatcher();
        }
        if ($selector === '*') { // all
            return $this->globalSelectorMatcher();
        } elseif (preg_match('/^[a-z0-9]+$/', strtolower($selector)) === 1) { // tagname
            return $this->tagSelectorMatcher($selector);
        } elseif (preg_match('/^([a-z0-9]*)\[([^\^\$\*\=]+)(^|\$|\*)?\=\"(.+)\"\]$/', strtolower($selector), $matches) === 1) {
            // [attribute="value"] or tagname[attribute="value"]
            return $this->attributeSelectorMatcher($matches[2], $matches[4], $matches[1], $matches[3]);
        } elseif (preg_match('/^[a-z0-9]+#.+$/', strtolower($selector)) === 1) { // tagname#id
            $parts = explode('#', $selector, 2);

            return $this->idSelectorMatcher($parts[1], $parts[0]);
        } elseif (preg_match('/^[a-z0-9]+\..+$/', strtolower($selector)) === 1) { // tagname.classname
            $parts = explode('.', $selector, 2);

            return $this->classSelectorMatcher($parts[1], $parts[0]);
        } elseif (substr($selector, 0, 1) === '#') { // #id
            return $this->idSelectorMatcher(substr($selector, 1));
        } elseif (substr($selector, 0, 1) === '.') { // .classname
            return $this->classSelectorMatcher(substr($selector, 1));
        } else {
            foreach ($this->selectorParserRegistry as $customSelector) {
                if ($customSelector->match($selector)) {
                    return $customSelector->parse($selector);
                }
            }
        }

        $message = "Unsupported selector. Default supported selectors are " .
            "'tagname', '#id', '.classname', '[attribute=\"value\"]', " .
            "'tagname#id', 'tagname.classname' and 'tagname[attribute=\"value\"]'";

        throw new \InvalidArgumentException($message);
    }

    /**
     * Method returns filter action based on passed selector and returns callable to be applied
     * or false if can't recognize filter within passed selector
     *
     * @param string $selector
     *
     * @return \Closure|null
     */
    protected function getFilter($selector)
    {
        $filter = null;
        if (preg_match($this->selectorFilterMatcher(), strtolower($selector), $matches) === 1) {
            // Strip full match and leave only filter name and params if available
            $filter = array_slice($matches, 1);

            $filter = $this->__getFilterCallback(Text::camelize($filter[0], '-'), array_slice($filter, 1));
        }

        return $filter;
    }

    /**
     * Returns filter callback based on passed method name and params
     *
     * @param string $method
     * @param array  $params
     *
     * @return \Closure|null
     */
    private function __getFilterCallback($method, $params = [])
    {
        $filter = null;
        // Check if filter is one of built in filters
        if (in_array($method, $this->builtInFilters)) {
            $filter = function (ElementList $elements) use ($method, $params) {
                if (is_array($params) || $params instanceof \Traversable) {
                    return $elements->{$method}(...$params);
                } else {
                    return $elements->{$method}();
                }
            };
        } else {
            // Check if any of registered selector parsers has passed filter
            foreach ($this->selectorParserRegistry as $customSelector) {
                $method = 'filter' . ucfirst($method);
                // Check for specific filter
                if (Utils::hasPublicMethod($customSelector, $method)) {
                    $filter = function (ElementList $elements) use ($customSelector, $method, $params) {
                        return $customSelector->{$method}($elements, ...$params);
                    };
                    // Check for general filter method
                } elseif (Utils::hasPublicMethod($customSelector, 'filter')) {
                    $filter = function (ElementList $elements) use ($customSelector, $params) {
                        return call_user_func_array([$customSelector, 'filter'], array_merge([$elements], $params));
                    };
                }
            }
        }

        return $filter;
    }

    /**
     * Returns whether passed tagName is void element (doesn't have closing tag)
     *
     * @param string $tagName
     *
     * @return bool
     */
    protected function isVoidElement($tagName)
    {
        return in_array($tagName, $this->voidElements);
    }

    /**
     * Finds specific elements based on passed selector and cache result for later use
     *
     * @param string   $originalSelector
     * @param string   $selector
     * @param callable $filter
     * @param string   $elementClass
     * @param array    $elementConfig
     *
     * @return ElementList
     */
    private function __findElements(
        $originalSelector,
        $selector,
        $filter = null,
        $elementClass = null,
        $elementConfig = []
    ) {
        if (Utils::match(
            $this->parseSelector($selector),
            $this->content,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            $elements = [];
            foreach ($matches as $no => $match) {
                $tagName = $match[1][0];
                if ($tagName === 'html') {
                    continue;
                }
                $startPosition = $match[0][1];

                if (!$this->isVoidElement($tagName)) {
                    $innerElementCount = 0;

                    /*
                     * We search for all opened and closed tags with same tag name after container opening tag.
                     */
                    $matchingTagsFound = Utils::match(
                        '/<(\/?)' . preg_quote($tagName) . '[^>]*>/',
                        $this->content,
                        $tags,
                        PREG_OFFSET_CAPTURE,
                        $startPosition + strlen($match[0][0])
                    );

                    if ($matchingTagsFound) {
                        // Go through all matching tags and find container closing tag
                        foreach ($tags as $i => $tag) {
                            $isClosingTag = $tag[1][0] === '/';
                            if (!$isClosingTag) {
                                $innerElementCount++;
                            } else {
                                if ($innerElementCount > 0) {
                                    $innerElementCount--;
                                } else {
                                    // This is container closing tag
                                    $endPosition = $tag[0][1] + strlen('</' . $tagName . '>');
                                    $length = $endPosition - $startPosition;
                                    $html = mb_substr($this->content, $startPosition, $length);
                                    // Add element to element collection
                                    $elements[] = $this->createElement($html,
                                        $startPosition,
                                        $elementClass,
                                        $elementConfig);
                                    break;
                                }
                            }
                        }
                    } else {
                        return new ElementList([]);
                    }
                } else {
                    // This is container closing tag
                    $endPosition = $startPosition + mb_strlen($match[0][0]);
                    $length = $endPosition - $startPosition;
                    $html = mb_substr($this->content, $startPosition, $length);
                    // Add element to element collection
                    $elements[] = $this->createElement($html, $startPosition, $elementClass, $elementConfig);
                }
            }

            $elements = new ElementList($elements);
            $this->queryResultCache[$originalSelector] =& $elements;

            if ($filter !== null) {
                return $filter($this->queryResultCache[$originalSelector]);
            }

            return $this->queryResultCache[$originalSelector];
        }

        $this->queryResultCache[$originalSelector] = false;

        return new ElementList([]);
    }
}
