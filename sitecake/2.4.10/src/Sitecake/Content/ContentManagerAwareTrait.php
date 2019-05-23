<?php

namespace Sitecake\Content;

use Sitecake\Content\DOM\Query\SelectorInterface;

trait ContentManagerAwareTrait
{
    /**
     * @var ContentManager
     */
    protected $contentManager;

    /**
     * Returns list of content containers
     *
     * @param string|callable|array|null $filter
     *
     * @return ElementList
     */
    public function getContentContainers($filter = null)
    {
        return $this->contentManager->listContentContainers($filter);
    }

    /**
     * Returns list of menus
     *
     * @param string|callable|array|null $filter
     *
     * @return ElementList
     */
    public function getMenus($filter = null)
    {
        return $this->contentManager->listMenus($filter);
    }

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
        return $this->contentManager->query($selector, $filter, $params);
    }

    /**
     * Appends passed child element to passed parent element if found
     *
     * @param Element $parent
     * @param Element $child
     *
     * @return $this
     */
    public function appendTo(Element $parent, Element $child)
    {
        $this->contentManager->appendTo($parent, $child);

        return $this;
    }

    /**
     * Inserts new element before existing element
     *
     * @param Element $newElement
     * @param Element $refElement
     *
     * @return $this
     */
    public function insertBefore(Element $newElement, Element $refElement)
    {
        $this->contentManager->insertBefore($newElement, $refElement);

        return $this;
    }

    /**
     * Inserts new element after existing element
     *
     * @param Element $newElement
     * @param Element $refElement
     *
     * @return $this
     */
    public function insertAfter(Element $newElement, Element $refElement)
    {
        $this->contentManager->insertAfter($newElement, $refElement);

        return $this;
    }

    /**
     * Removes passed element
     *
     * @param Element $refElement
     *
     * @return $this
     */
    public function removeElement(Element $refElement)
    {
        $this->contentManager->removeElement($refElement);

        return $this;
    }

    /**
     * Replaces existing element with new passed element
     *
     * @param Element $newElement
     * @param Element $oldElement
     *
     * @return $this
     */
    public function replaceElement(Element $newElement, Element $oldElement)
    {
        $this->contentManager->replaceElement($newElement, $oldElement);

        return $this;
    }
}
