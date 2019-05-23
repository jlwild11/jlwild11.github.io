<?php

namespace Sitecake\Content\DOM\Element;

use Sitecake\Content\Element;

class Menu extends Element
{
    /**
     * Base class for identifying container
     */
    const BASE_CLASS = 'sc-nav';

    /**
     * Default menu identifier.
     * If menu isn't identifier it's identifier would be this value
     */
    const DEFAULT_MENU_IDENTIFIER = 'main';

    /**
     * Default template for rendering menu items
     */
    const DEFAULT_TEMPLATE = '<li><a class="${active}" href="${url}" title="${titleText}">${title}</a></li>';

    /**
     * Menu item types
     */
    const ITEM_TYPE_DEFAULT = 'default';
    const ITEM_TYPE_PAGE = 'page';
    const ITEM_TYPE_CUSTOM = 'custom';

    /**
     * Default configuration used by element
     *
     * @var array
     */
    protected $defaultConfig = [
        'baseClass' => 'sc-nav'
    ];

    /**
     * Menu type
     *
     * @var string
     */
    protected $type;

    /**
     * At the moment all menus are treated as main. When menu manager is implemented this will deffer from menu to menu
     * @var string
     */
    protected $name = self::DEFAULT_MENU_IDENTIFIER;

    /**
     * Menu items template
     *
     * @var string
     */
    protected $template = self::DEFAULT_TEMPLATE;

    /**
     * Class name to use for current active menu item
     *
     * @var string
     */
    protected $activeClass;

    /**
     * Collection of menu items.
     * Each item in collection have properties below:
     *      + type - possible values are 'default', 'page' and 'custom'
     *      + text - item text
     *      + url - item url
     *      + title - title to use on <a> tag
     *
     * @var array
     */
    protected $items;

    public function __construct($html, array $config = [])
    {
        parent::__construct($html, $config);

        $class = $this->getAttribute('class');
        $baseClass = $this->getConfig('baseClass');
        if (preg_match('/(?:^|\s)' . preg_quote($baseClass) . '(?:\-([^\s]+))/', $class, $matches) === 1) {
            $this->name = $matches[1];
        } else {
            $this->name = self::DEFAULT_MENU_IDENTIFIER;
        }
    }

    /**
     * Returns container name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets template for menu items
     *
     * @param $template
     */
    public function setTemplate($template)
    {
        $this->template = $template;
    }

    /**
     * Sets active menu item class
     *
     * @param $class
     */
    public function setActiveClass($class)
    {
        $this->activeClass = $class;
    }

    /**
     * Builds internal items array based on HTML source
     */
    protected function findItems()
    {
        foreach ($this->domElement->getElementsByTagName('a') as $no => $menuItem) {
            /** @var \DOMElement $menuItem */
            $data = [
                'type' => self::ITEM_TYPE_DEFAULT,
                'text' => $menuItem->textContent,
                'url' => $menuItem->getAttribute('href'),
                'title' => $menuItem->getAttribute('title') ?: $menuItem->textContent
            ];

            if ($target = $menuItem->getAttribute('target')) {
                $data['target'] = $target;
            }

            $this->items[] = $data;
        }
    }

    public function html($content = null)
    {
        $menuItems = '';
        foreach ($this->items() as $no => $item) {
            if (isset($item['target']) && !empty($item['target'])) {
                $itemHTML = str_replace('${url}', $item['url'] . '" target="' . $item['target'], $this->template);
            } else {
                $itemHTML = str_replace('${url}', $item['url'], $this->template);
            }
            $itemHTML = str_replace('${title}', $item['text'], $itemHTML);
            $itemHTML = str_replace('${order}', $no, $itemHTML);
            $itemHTML = str_replace(
                '${titleText}',
                (isset($item['title']) ? $item['title'] : $item['text']),
                $itemHTML
            );

            if (strpos($itemHTML, '${active}') !== false) {
                if (isset($content) && is_callable($content) && $content($item['url'])) {
                    $itemHTML = str_replace('${active}', $this->activeClass, $itemHTML);
                } else {
                    $itemHTML = str_replace('${active}', '', $itemHTML);
                }
            }

            $menuItems .= $itemHTML;
        }

        parent::html($menuItems);
    }

    /**
     * Gets/sets menu item collection.
     * If second parameter is passed each menu item would be processed by passed callable
     *
     * @param array $items
     * @param callable|null $process
     *
     * @return array|null
     */
    public function items($items = null, $process = null)
    {
        if ($items === null) {
            if (!isset($this->items)) {
                $this->findItems();
            }
            return $this->items;
        }

        if (empty($process)) {
            return $this->items =  $items;
        } else {
            return $this->items = array_map($process, $items);
        }
    }
}
