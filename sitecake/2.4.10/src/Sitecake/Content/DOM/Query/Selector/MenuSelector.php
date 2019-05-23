<?php

namespace Sitecake\Content\DOM\Query\Selector;

use Sitecake\Content\DOM\Query\SelectorMatcherAwareTrait;
use Sitecake\Content\DOM\Query\SelectorInterface;
use Sitecake\Content\Element;
use Sitecake\Content\ElementList;

class MenuSelector implements SelectorInterface
{
    use SelectorMatcherAwareTrait;

    const MAIN_MENU = 'main';

    protected $baseClass = 'sc-nav';

    public function __construct($baseClass = null)
    {
        if ($baseClass !== null) {
            $this->baseClass = $baseClass;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMatcher()
    {
        return $this->attributeSelectorMatcher('class', $this->baseClass, null, '*');
    }

    /**
     * {@inheritdoc}
     */
    public function getFilter($filter = null, $params = [])
    {
        if ($filter === self::MAIN_MENU) {
            return function (ElementList $elements) {
                return $elements->filter(function (Element $element) {
                    return preg_match(
                            '/(^|\s)' . preg_quote($this->baseClass) . '(\s|$)/',
                            $element->getAttribute('class')
                        ) === 1;
                });
            };
        } elseif (is_string($filter)) {
            return function (ElementList $elements) use ($filter) {
                return $elements->filter(function (Element $element) use ($filter) {
                    return preg_match(
                            '/(^|\s)' . preg_quote($this->baseClass) . '\-' . preg_quote($filter) . '(\s|$)/',
                            $element->getAttribute('class')
                        ) === 1;
                });
            };
        } elseif (is_array($filter)) {
            return function (ElementList $elements) use ($filter) {
                return $elements->filter(function (Element $element) use ($filter) {
                    $filter = array_map(function ($containerName) {
                        return preg_quote($containerName);
                    }, $filter);
                    return preg_match(
                            '/(^|\s)' . preg_quote($this->baseClass) . '\-' . implode('|', $filter) . '(\s|$)/',
                            $element->getAttribute('class')
                        ) === 1;
                });
            };
        }

        return null;
    }
}
