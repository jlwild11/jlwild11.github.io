<?php

namespace Sitecake\Content\DOM\Query;

interface SelectorInterface
{
    /**
     * Returns regular expression which will be used to match HTML content
     * for  group of elements
     *
     * @return string
     */
    public function getMatcher();

    /**
     * Method receives filter passed as param or via selector to HTMLQueryTrait::query method and returns callable
     * that as a parameter receives ElementList created as a result of applying pattern returned
     * from getMatcher method.
     * If no filtering is required, method should return NULL.
     *
     * @param string|null $filter
     * @param array $params
     *
     * @return null|callable
     */
    public function getFilter($filter = null, $params = []);
}
