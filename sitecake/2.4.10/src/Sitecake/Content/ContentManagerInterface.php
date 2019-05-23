<?php

namespace Sitecake\Content;

interface ContentManagerInterface
{
    /**
     * Returns Collection of all content containers in source or filtered by passed parameter.
     * Filter can be one of pre-defined filter constants
     *  + ContentContainerSelector::NAMED_CONTAINERS,
     *  + ContentContainerSelector::UNNAMED_CONTAINERS
     * or container name(s)
     *
     * @param string|callable|array|null $filter
     *
     * @return ElementList
     */
    public function listContentContainers($filter = null);

    /**
     * Returns Collection of all menus in source or filtered by passed parameter.
     * Filter can be  MenuSelector::MAIN_MENU or menu name(s)
     *
     * @param string|callable|array|null $filter
     *
     * @return ElementList
     */
    public function listMenus($filter = null);
}
