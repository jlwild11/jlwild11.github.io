<?php

namespace Sitecake\Content;

use Cake\Event\Event;
use Sitecake\Content\DOM\Element\Menu;
use Sitecake\PageManager\PageManager;
use Sitecake\Resources\ResourceManager;
use Sitecake\Resources\SourceFile;
use Sitecake\Site;
use Sitecake\Sitecake;
use Sitecake\Util\HtmlUtils;
use Sitecake\Util\Utils;

class MenuManager
{
    /**
     * Page manager instance
     *
     * @var PageManager
     */
    protected $pageManager;

    /**
     * Resource manager instance
     *
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * HTML template for menu item
     *
     * @var string
     */
    protected $itemTemplate;

    /**
     * Class name for active menu item
     *
     * @var string
     */
    protected $activeClass;

    /**
     * List of page paths needed for referencing menu items
     *
     * @var array
     */
    protected $pagePaths = null;

    /**
     * MenuManager constructor.
     *
     * @param Site $site
     * @param PageManager $pageManager
     * @param ResourceManager $resourceManager
     */
    public function __construct(Site $site, PageManager $pageManager, ResourceManager $resourceManager)
    {
        $this->pageManager = $pageManager;

        $this->resourceManager = $resourceManager;
        $this->resourceManager->getEventManager()->on('ResourceManager.onDraftCreate', [$this, 'onDraftCreate']);
        $this->resourceManager->getEventManager()->on('ResourceManager.onResourceRemove', [$this, 'onResourceRemove']);
        
        $this->itemTemplate = Sitecake::getConfig('menus.item_template');
        $this->activeClass = Sitecake::getConfig('menus.active_class');
    }

    /**
     * Checks whether path is page path
     *
     * @param string $path
     *
     * @return bool
     */
    protected function isPagePath($path)
    {
        if ($this->pagePaths === null) {
            $this->pagePaths = $this->pageManager->loadPageFilePaths();
        }
        return in_array($path, $this->pagePaths);
    }

    /**
     * Loads navigation sections found within passed page
     *
     * @param Menu   $menu
     * @param string $path
     */
    public function saveMenu(Menu $menu, $path)
    {
        $menusMetadata = Sitecake::cache()->get('menus');

        $name = $menu->getName();

        if (!isset($menusMetadata[$name])) {
            $menusMetadata[$name] = [
                'pages' => [$path],
                'items' => []
            ];

            $menuItems = $menu->items();
            $menusMetadata[$name]['items'] = [];

            if ($menuItems) {
                foreach ($menuItems as $no => &$menuItem) {
                    if (Utils::isExternalLink($menuItem['url'])
                        || HtmlUtils::isAnchorLink($menuItem['url'])
                    ) {
                        $menuItem['type'] = Menu::ITEM_TYPE_CUSTOM;
                    } else {
                        $isPagePath = $this->isPagePath($path);
                        $referencedPagePath = $this->resourceManager->urlToPath(
                            $menuItem['url'],
                            $isPagePath ? $path : ''
                        );
                        if ($referencedPagePath !== false && $isPagePath) {
                            $menuItem['type'] = Menu::ITEM_TYPE_PAGE;
                            $menuItem['reference'] = $referencedPagePath;
                        }
                    }
                    $menusMetadata[$name]['items'][] = $menuItem;
                }
            }
        } elseif (array_search($path, $menusMetadata[$name]['pages']) === false) {
            array_push($menusMetadata[$name]['pages'], $path);
        }

        Sitecake::cache()->save('menus', $menusMetadata);

        if (!empty($menusMetadata[$name]['items'])) {
            return;
        }
    }

    /**
     * Updates all menus in all pages with new menu content
     *
     * @param array $menuData      Menus metadata
     * @param array $pages         Map of updated page paths where for updated paths keys are old paths and values are
     *                             new paths, and for deleted paths, keys are numeric
     */
    public function updateMenus($menuData, $pages)
    {
        $metadata = Sitecake::cache()->get('menus');

        // We need only to update existing menus. If there is menu that doesn't exist, we do nothing
        $sentMenus = [];
        foreach ($menuData as $no => $menu) {
            array_push($sentMenus, $menu['name']);
        }

        foreach ($metadata as $name => &$menuMetadata) {
            if (!in_array($name, $sentMenus)) {
                continue;
            }
            $parentPages = [];
            foreach ($menuMetadata['pages'] as $no => $path) {
                // Check if path is changed or deleted
                if (array_key_exists($path, $pages)) {
                    $path = $pages[$path];
                } elseif (is_numeric(array_search($path, $pages))) {
                    continue;
                }

                array_push($parentPages, $path);

                $draftPath = $this->resourceManager->getDraftPath($path);

                $sourceFile = new SourceFile($this->resourceManager->read($draftPath), $path);

                $menuMetadata['items'] = $sourceFile->saveMenus(
                    $name,
                    $this->itemTemplate,
                    array_values($menuData[array_search($name, $sentMenus)]['items']),
                    function ($item) use ($path, $pages) {
                        if ($item['type'] == Menu::ITEM_TYPE_PAGE) {
                            if (array_key_exists($item['reference'], $pages)) {
                                $item['reference'] = $pages[$item['reference']];
                            }
                            $item['url'] = $this->resourceManager->pathToUrl($item['reference'], $path);

                            return $item;
                        }

                        return $item;
                    },
                    $this->activeClass,
                    function ($url) use ($path) {
                        if (Utils::isExternalLink($url)
                            || HtmlUtils::isAnchorLink($url)
                        ) {
                            return false;
                        }

                        return $path == $this->resourceManager->urlToPath($url, $path);
                    }
                );

                $this->resourceManager->update($draftPath, (string)$sourceFile);
                $this->resourceManager->markPathDirty($draftPath);

                // Update last modified time in metadata
                $this->resourceManager->saveLastModified($draftPath);
            }
            $menuMetadata['pages'] = array_unique($parentPages);
        }
        Sitecake::cache()->save('menus', $metadata);
    }

    /**
     * Returns array of stored menus
     *
     * @return array
     */
    public function listMenus()
    {
        $return = [];
        $menus = Sitecake::cache()->get('menus', []);

        foreach ($menus as $name => $menu) {
            $return[$name] = array_values($menu['items']);
        }

        return $return;
    }

    /**
     * onDraftCreate callback.
     *
     * @param Event  $event
     * @param string $resource
     */
    public function onDraftCreate(Event $event, $resource)
    {
        if ($resource instanceof SourceFile) {
            // Cache menu metadata
            $this->processMenus($resource);
        }
    }

    /**
     * Check for existing navigation in source file and process them
     *
     * @param SourceFile $sourceFile
     */
    protected function processMenus(SourceFile $sourceFile)
    {
        $path = $sourceFile->getPath();
        $menus = $sourceFile->menus();
        $processed = [];
        foreach ($menus as $menu) {
            // Avoid processing same menus
            if (in_array($menu->getName(), $processed)) {
                continue;
            }
            $this->saveMenu($menu, $path);
            $processed[] = $menu->getName();
        }
    }

    /**
     * onResourceRemove callback
     *
     * @param Event $event
     * @param string $path
     */
    public function onResourceRemove(Event $event, $path)
    {
        $menusMetadata = Sitecake::cache()->get('menus', []);
        if (!empty($menusMetadata)) {
            foreach ($menusMetadata as &$menu) {
                if (($index = array_search($path, $menu['pages'])) !== false) {
                    unset($menu['pages'][$index]);
                }
            }
        }
        Sitecake::cache()->save('menus', $menusMetadata);
    }
}
