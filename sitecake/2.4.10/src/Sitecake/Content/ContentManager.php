<?php

namespace Sitecake\Content;

use Sitecake\Content\DOM\Element\ContentContainer;
use Sitecake\Content\DOM\Element\Menu;
use Sitecake\Content\DOM\HTMLManipulationTrait;
use Sitecake\Content\DOM\Query\Selector\ContentContainerSelector;
use Sitecake\Content\DOM\Query\Selector\MenuSelector;
use Sitecake\Cache;

class ContentManager implements ContentManagerInterface
{
    use HTMLManipulationTrait {
        getContent as protected _getContent;
    }

    /**
     * @var Cache
     */
    protected $metadata;

    /**
     * Array containing all pages with path
     *
     * @var \ArrayObject<string, \ArrayObject<string, string|Sitecake\Page>>
     */
    protected $modifiedPages = [];

    /**
     * Class used to identify content containers
     *
     * @var string
     */
    protected $contentContainerBaseClass = 'sc-content';

    /**
     * Class used to identify menu
     *
     * @var string
     */
    protected $menuBaseClass = 'sc-nav';

    /**
     * Cache of content container names
     *
     * @var array
     */
    protected $contentContainerNames = null;

    /**
     * {@inheritdoc}
     */
    public function listContentContainers($filter = null)
    {
        $contentContainers = $this->query(
            new ContentContainerSelector($this->contentContainerBaseClass), $filter, [
                'elementClass' => ContentContainer::class,
                'elementConfig' => ['baseClass' => $this->contentContainerBaseClass]
            ]
        );

        // Cache content container names if no filter is passed
        if (!$filter) {
            foreach ($contentContainers as $container) {
                $this->contentContainerNames[] = $container->getName();
            }
        }

        return $contentContainers;
    }

    /**
     * Returns array of content container names
     *
     * @return array
     */
    public function listContainerNames()
    {
        if ($this->contentContainerNames === null) {
            $this->listContentContainers();
        }

        return $this->contentContainerNames;
    }

    /**
     * {@inheritdoc}
     */
    public function listMenus($filter = null)
    {
        return $this->query(
            new MenuSelector($this->menuBaseClass), $filter, [
                'elementClass' => Menu::class,
                'elementConfig' => ['baseClass' => $this->menuBaseClass]
            ]
        );
    }

    /**
     * Updates content from objects and returns it
     *
     * @return string
     */
    public function getContent()
    {
        $updateMap = [];
        foreach ($this->elementsMetadata as $startPosition => $metadata) {
            if (in_array($startPosition, $updateMap)) {
                continue;
            }
            $this->content = $this->__updateContent($this->content, $metadata, $updateMap);
        }

        return $this->content;
    }

    /**
     * Updates passed content for passed element metadata and returns updated content
     *
     * @param string $content   Content to update
     * @param array  $metadata  Element metadata
     * @param array  $updateMap End position of element within content
     *
     * @return string New element's HTML after indentation is applied
     */
    private function __updateContent($content, $metadata, &$updateMap = [])
    {
        /**
         * @var string   $original
         * @var int      $startPosition
         * @var int      $endPosition
         * @var int      $index
         * @var array    $children
         * @var int|null $parent
         */
        extract($metadata);
        $element = $this->elements[$index];
        if (!empty($children)) {
            foreach ($children as $childIndex) {
                $childMetadata = $this->elementsMetadata[$childIndex];
                $content = $this->__updateContent($content, $childMetadata, $updateMap);
            }
        }

        if ($parent !== null) {
            $parentHTML = $this->elementsMetadata[$parent]['original'];
            $occurrence = strpos($parentHTML, $original);
            if ($occurrence !== false) {
                $parentHTML = substr_replace($parentHTML, $element->outerHtml(), $occurrence, strlen($original));
                $this->elementsMetadata[$parent]['original'] = $parentHTML;

                return $content;
            }
        }

        if ($element->isModified()) {
            $occurrence = strpos($content, $original);
            if ($occurrence !== false) {
                $content = substr_replace($content, $element->outerHtml(), $occurrence, strlen($original));
                $updateMap[] = $index;
            }
        }

        return $content;
    }
}
