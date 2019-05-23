<?php

namespace Sitecake\Content\DOM\Query\Selector;

use Sitecake\Content\DOM\Element\ContentContainer;
use Sitecake\Content\DOM\Query\SelectorMatcherAwareTrait;
use Sitecake\Content\DOM\Query\SelectorInterface;
use Sitecake\Content\Element;
use Sitecake\Content\ElementList;

class ContentContainerSelector implements SelectorInterface
{
    use SelectorMatcherAwareTrait;

    const NAMED_CONTAINERS_FILTER = 'named';

    const UNNAMED_CONTAINERS_FILTER = 'unnamed';

    const GENERATED_CONTAINERS_FILTER = 'generated';

    protected $baseClass = 'sc-content';

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
        if ($filter === self::NAMED_CONTAINERS_FILTER) {
            return function (ElementList $elements) {
                return $elements->filter(function (Element $element) {
                    return preg_match(
                            '/(^|\s)' . preg_quote($this->baseClass) . '\-[^\s]+/',
                            $element->getAttribute('class')
                        ) === 1;
                });
            };
        } elseif ($filter === self::UNNAMED_CONTAINERS_FILTER) {
            return function (ElementList $elements) {
                return $elements->filter(function (Element $element) {
                    return preg_match(
                            '/(^|\s)' . preg_quote($this->baseClass) . '(\s|$)/',
                            $element->getAttribute('class')
                        ) === 1;
                });
            };
        } elseif ($filter === self::GENERATED_CONTAINERS_FILTER) {
            return function (ElementList $elements) {
                return $elements->filter(function (Element $element) {
                    return preg_match(
                            '/(^|\s)' . preg_quote($this->baseClass) . '\-_cnt_[0-9]+(\s|$)/',
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
