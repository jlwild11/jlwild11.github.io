<?php

namespace Sitecake\Content\DOM\Query;

trait SelectorMatcherAwareTrait
{
    protected $selectorPatterns = [
        'whitespace' => '[\x20\t\r\n\f]',
        'identifier' => '(?:\\\.|[\w-]|[^\0-\xa0])+',
        'tagName' => '<(%s)(?:[\x20\t\r\n\f][^>]*>|>)',
        'class' => '<(%s)[^>]+class=(?:"|\')(?:(?!%s|>).)*%s(?:[\x20\t\r\n\f]|"|\')[^>]*>',
        'attr' => '<(%s)[^>]+%s=(?:"|\')%s(?:"|\')[^>]*>',
        'attrStarts' => '<(%s)[^>]+%s=(?:"|\')%s[^>]*>',
        'attrEnds' => '<(%s)[^>]+%s=(?:"|\')(?:(?!%s|>).)*%s(?:"|\')[^>]*>',
        'attrContains' => '<(%s)[^>]+%s=(?:"|\')(?:(?!%s|>).)*%s[^>]*>',
        'filter' => '\:([^\(]+)(?:\(([^\)]*)\))?$'
    ];

    protected $selectorFilterPattern = '/\:([^\(]+)(?:\(([^\)]*)\))?$/';

    /**
     * Returns regular expression for global selector (*)
     *
     * @return string
     */
    protected function globalSelectorMatcher()
    {
        return '/' . sprintf($this->selectorPatterns['tagName'], $this->selectorPatterns['identifier']) . '/i';
    }

    /**
     * Returns regular expression to match content for tag name (tagname - div, a, img...)
     *
     * @param string $tagName
     *
     * @return string
     */
    protected function tagSelectorMatcher($tagName)
    {
        return '/' . sprintf($this->selectorPatterns['tagName'], preg_quote($tagName, '/')) . '/i';
    }

    /**
     * Returns regular expression to match content for attribute value with possibility to match tag name
     * ([attribute(^|$|*)="value"] or tagname[attribute(^|$|*)="value"])
     * There is possibility to pass equivalency parameter to match start, end or containment of value in attribute
     *
     * @param string $attribute
     * @param string $value
     * @param string|null [optional] $tagName
     * @param string|null [optional] $equivalency
     *
     * @return string
     */
    protected function attributeSelectorMatcher($attribute, $value, $tagName = null, $equivalency = null)
    {
        if (!$tagName) {
            $tagName = $this->selectorPatterns['identifier'];
        }
        if ($equivalency !== null) {
            switch ($equivalency) {
                case '^':
                    return '/' . sprintf(
                            $this->selectorPatterns['attrStarts'],
                            $tagName,
                            preg_quote($attribute, '/'),
                            preg_quote($value, '/')
                        ) . '/i';
                case '$':
                    return '/' . sprintf(
                            $this->selectorPatterns['attrEnds'],
                            $tagName,
                            preg_quote($attribute, '/'),
                            preg_quote($value, '/'),
                            preg_quote($value, '/')
                        ) . '/i';
                case '*':
                    return '/' . sprintf(
                            $this->selectorPatterns['attrContains'],
                            $tagName,
                            preg_quote($attribute, '/'),
                            preg_quote($value, '/'),
                            preg_quote($value, '/')
                        ) . '/i';
                case '~':
                    return '/' . sprintf(
                            $this->selectorPatterns['attr'],
                            $tagName,
                            preg_quote($attribute, '/'),
                            $value
                        ) . '/i';
            }
        }

        return '/' . sprintf(
                $this->selectorPatterns['attr'],
                $tagName,
                preg_quote($attribute, '/'),
                preg_quote($value, '/')
            ) . '/i';
    }

    /**
     * Returns regular expression to match content for id value with possibility to match tag name (#id or tagname#id)
     *
     * @param string $id
     * @param string|null [optional] $tagName
     *
     * @return string
     */
    protected function idSelectorMatcher($id, $tagName = null)
    {
        return '/' . sprintf(
                $this->selectorPatterns['attr'],
                ($tagName === null ? $this->selectorPatterns['identifier'] : preg_quote($tagName, '/')),
                'id',
                preg_quote($id, '/')
            ) . '/i';
    }

    /**
     * Returns regular expression to match content for class value with possibility to match tag name
     * (.class or tagname.class)
     *
     * @param string $class
     * @param string|null [optional] $tagName
     *
     * @return string
     */
    protected function classSelectorMatcher($class, $tagName = null)
    {
        return '/' . sprintf(
                $this->selectorPatterns['class'],
                ($tagName === null ? $this->selectorPatterns['identifier'] : preg_quote($tagName, '/')),
                preg_quote($class, '/'),
                preg_quote($class, '/')
            ) . '/i';
    }

    /**
     * Returns regular expression to match filter (:filter or :filter(param))
     *
     * @return string
     */
    protected function selectorFilterMatcher()
    {
        return '/' . $this->selectorPatterns['filter'] . '/';
    }
}
