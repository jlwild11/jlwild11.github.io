<?php

namespace Sitecake\Content\DOM;

use Sitecake\Content\ContentAwareTrait;
use Sitecake\Content\DOM\Query\HTMLQueryTrait;
use Sitecake\Content\Element;

trait HTMLManipulationTrait
{
    use ContentAwareTrait;
    use HTMLQueryTrait;

    /**
     * Global element index inside Collection
     *
     * @var int
     */
    protected $elementIndex = 0;

    /**
     * @var Element[]
     */
    protected $elements;

    /**
     * Map of hashes of elements that are queried, indexed by global index.
     *
     * @var array[]
     */
    protected $elementsMetadata = [];

    /**
     * Creates element instance based on passed HTML content and caches it for later use
     *
     * @param string $html          Element to add
     * @param int    $startPosition Starting position of an element in a content
     * @param string $elementClass  Class to use when instantiating elements
     * @param array  $elementConfig Configuration to be passed when instantiating elements
     *
     * @return Element
     */
    public function createElement($html, $startPosition, $elementClass = null, $elementConfig = [])
    {
        if ($elementClass !== null && is_subclass_of($elementClass, Element::class)) {
            $element = new $elementClass($html, $elementConfig);
        } else {
            $element = new Element($html, $elementConfig);
        }

        // Cache element for later use
        return $this->__cacheElement($element, $html, $startPosition);
    }

    /**
     * Adds element to internal data structures
     *
     * @param Element $element       Element to add
     * @param string  $html          Elements HTML
     * @param int     $startPosition Start position of element within content
     *
     * @return Element
     */
    private function __cacheElement(Element $element, $html, $startPosition)
    {
        $this->elements[$this->elementIndex] =& $element;
        $endPosition = $startPosition + mb_strlen($html);
        $this->elementsMetadata[$this->elementIndex] = [
            'original' => $html,
            'startPosition' => $startPosition,
            'endPosition' => $endPosition,
            'index' => $this->elementIndex,
            'children' => [],
            'parent' => null
        ];

        // Find and store elements parent and update children metadata within that parent
        $map = array_reverse($this->elementsMetadata);
        foreach ($map as $metadata) {
            $start = $metadata['startPosition'];
            if ($start < $startPosition && $metadata['endPosition'] > $endPosition) {
                // Store start position of child element
                $this->elementsMetadata[$metadata['index']]['children'][] = $this->elementIndex;
                $this->elementsMetadata[$this->elementIndex]['parent'] = $metadata['index'];
                break;
            }
        }

        $this->elementIndex++;

        return $element;
    }

    /**
     * Updates positions of element that comes after passed referent position
     *
     * @param int $refPosition
     * @param int $diff
     */
    private function __updatePositions($refPosition, $diff)
    {
        foreach ($this->elementsMetadata as &$metadata) {
            if ($metadata['startPosition'] > $refPosition) {
                $metadata['startPosition'] += $diff;
                $metadata['endPosition'] += $diff;
            }
        }
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
        foreach ($this->elements as $index => $element) {
            if ($element === $parent) {
                $child = clone $child;
                $html = $child->outerHtml();
                $length = $child->length();
                $parent->html($parent->html() . $html);
                $parentHTML = $this->elementsMetadata[$index]['original'];
                $parentStartPosition = $this->elementsMetadata[$index]['startPosition'];
                $insertionPoint = strlen($parentHTML) - strlen('</' . $parent->tagName . '>');
                $childStartPosition = $parentStartPosition + $insertionPoint;
                $this->elementsMetadata[$index]['endPosition'] += $length;
                $this->__cacheElement($child, $html, $childStartPosition);
                $this->content = substr_replace($this->content, $html, $childStartPosition, 0);
                $this->__updatePositions($childStartPosition, $length);
            }
        }

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
        foreach ($this->elements as $index => $element) {
            if ($refElement === $element) {
                $newElement = clone $newElement;
                $insertionPoint = (int)$this->elementsMetadata[$index]['startPosition'];
                $elementHTML = $newElement->outerHtml();
                if ($this->elementsMetadata[$index]['parent'] !== null) {
                    $this->elements[(int)$this->elementsMetadata[$index]['parent']]->contentModified(true);
                }
                $this->__cacheElement($newElement, $elementHTML, $insertionPoint);
                $this->content = substr_replace($this->content, $elementHTML, $insertionPoint, 0);
                $this->__updatePositions($insertionPoint, mb_strlen($elementHTML));
                break;
            }
        };

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
        foreach ($this->elements as $index => $element) {
            if ($refElement === $element) {
                $newElement = clone $newElement;
                $endPosition = (int)$this->elementsMetadata[$index]['endPosition'];
                $insertionPoint = $endPosition + 1;
                $elementHTML = $newElement->outerHtml();
                if ($this->elementsMetadata[$index]['parent'] !== null) {
                    $this->elements[(int)$this->elementsMetadata[$index]['parent']]->contentModified(true);
                }
                $this->__cacheElement($newElement, $elementHTML, $insertionPoint);
                $this->content = substr_replace($this->content, $elementHTML, $insertionPoint, 0);
                $this->__updatePositions($endPosition, mb_strlen($elementHTML));
                break;
            }
        };

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
        foreach ($this->elements as $index => $element) {
            if ($refElement === $element) {
                if ($this->elementsMetadata[$index]['parent'] !== null) {
                    $this->elements[(int)$this->elementsMetadata[$index]['parent']]->contentModified(true);
                }
                $this->__removeElement($index);
                $this->content = str_replace($this->elementsMetadata['original'], '', $this->content);
                $this->__updatePositions(
                    (int)$this->elementsMetadata[$index]['startPosition'],
                    mb_strlen($this->elementsMetadata[$index]['original']) * -1
                );
            }
        };

        return $this;
    }

    /**
     * Removes element under passed index from internal data structures
     *
     * @param int $index
     */
    private function __removeElement($index)
    {
        if (!empty($this->elementsMetadata[$index]['children'])) {
            foreach ($this->elementsMetadata[$index]['children'] as $childIndex) {
                $this->__removeElement($childIndex);
            }
        }
        unset($this->elements[$index]);
        unset($this->elementsMetadata[$index]);
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
        foreach ($this->elements as $index => $element) {
            if ($oldElement === $element) {
                $newElement = clone $newElement;
                $insertionPoint = (int)$this->elementsMetadata[$index]['startPosition'];
                $newElementHTML = $newElement->outerHtml();
                if ($this->elementsMetadata[$index]['parent'] !== null) {
                    $this->elements[(int)$this->elementsMetadata[$index]['parent']]->contentModified(true);
                }
                $this->__cacheElement($newElement, $newElementHTML, $insertionPoint);
                $oldElementHTML = $this->elementsMetadata[$index]['original'];
                $this->__removeElement($index);
                $this->content = str_replace($oldElementHTML, $newElementHTML, $this->content);
                $this->__updatePositions(
                    $insertionPoint,
                    abs($oldElementHTML - $newElementHTML) * ($oldElementHTML > $newElementHTML ? -1 : 1)
                );
                break;
            }
        };

        return $this;
    }

    /**
     * Returns whether passed element is child element of passed parent
     *
     * @param Element $child
     * @param Element $parent
     *
     * @return bool
     */
    public function isChildOf(Element $child, Element $parent)
    {
        foreach ($this->elements as $index => $element) {
            if ($parent === $element && !empty($this->elementsMetadata[$index]['children'])) {
                foreach ($this->elementsMetadata[$index]['children'] as $childIndex) {
                    $childElement = $this->elements[$childIndex];
                    if ($childElement === $child) {
                        return true;
                    } elseif (!empty($this->elementsMetadata[$childIndex]['children'])) {
                        return $this->isChildOf($child, $childElement);
                    }
                }
            }
        }

        return false;
    }
}
