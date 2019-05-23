<?php

namespace Sitecake\Resources;

use Sitecake\Content\ContentManager;
use Sitecake\Content\ContentManagerAwareTrait;
use Sitecake\Content\DOM\Element\ContentContainer;
use Sitecake\Content\DOM\Element\Menu;
use Sitecake\Content\DOM\Element\Meta;
use Sitecake\Content\DOM\Element\Title;
use Sitecake\Content\Element;
use Sitecake\Content\ElementList;
use Sitecake\PageManager\TemplateInterface;
use Sitecake\Util\Beautifier;
use Sitecake\Util\Utils;

class SourceFile implements ResourceInterface, TemplateInterface
{
    use ContentManagerAwareTrait;

    /**
     * Page identifier
     *
     * @var mixed
     */
    protected $path;

    /**
     * Original source code
     *
     * @var string
     */
    protected $original;

    /**
     * Array of PHP code fragments replacements
     *
     * @var array
     */
    protected $phpTagsReplacements = [];

    /**
     * SourceFile constructor.
     *
     * @param string $source
     * @param string|null [optional] $path
     */
    public function __construct($source, $path = null)
    {
        $this->original = $source;

        if ($path !== null) {
            $this->path = $path;
        }

        // Replace all PHP code with script tags
        // TODO: 'Catastrophic backtracking' with big files and pure PHP files. Need to modify regex or figure out other way to exclude those files
        if (preg_match_all('/<(?:\?|\%)\=?(?:php)?(?:\s)(?:(?!\?>).)*(\?>)?/si', $source, $matches)) {
            $this->phpTagsReplacements = $matches[0];
            foreach ($matches[0] as $i => $match) {
                $uid = Utils::id();
                $this->phpTagsReplacements[$uid] = $match;
                $source = str_replace($match, '<script data-sc-script="' . $uid . '"></script>', $source);
            }
        }
        $this->contentManager = new ContentManager();
        $this->contentManager->setContent($source);
    }

    //<editor-fold desc="ResourceInterface implementation">

    /**
     * {@inheritdoc}
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        $content = $this->contentManager->getContent();
        foreach ($this->phpTagsReplacements as $uid => $original) {
            $content = str_replace('<script data-sc-script="' . $uid . '"></script>', $original, $content);
        }

        $this->original = $content;

        try {
            return Beautifier::indent($content);
        } catch (\Exception $e) {
            return $content;
        }
    }
    //</editor-fold>

    //<editor-fold desc="TemplateInterface implementation">

    /**
     * {@inheritdoc}
     */
    public function getSource()
    {
        return $this->original;
    }

    /**
     * {@inheritdoc}
     */
    public function evaluateSource()
    {
        ob_start();
        eval('?>' . $this->original);
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }
    //</editor-fold>

    //<editor-fold desc="Content containers related methods">

    /**
     * Sets content for passed container names
     *
     * @param string|array $containerName Container name or associative array where keys are container names and
     *                                    values are contents for each container
     * @param string|null  $content       [optional] Content to set
     *
     * @return void
     */
    public function setContainerContent($containerName, $content = null)
    {
        $filter = $containerName;
        if (is_array($containerName)) {
            $filter = array_keys($containerName);
        }
        $containers = $this->getContentContainers($filter);
        foreach ($containers as $container) {
            /** @var ContentContainer $container */
            if (is_array($containerName)) {
                $content = $containerName[$container->getName()];
            }
            $container->html($content);
        }
    }

    /**
     * Returns a list of container names contained in page.
     *
     * @return array a list of container names
     */
    public function containerNames()
    {
        return $this->contentManager->listContainerNames();
    }

    /**
     * Returns weather page contains specific container (does it contains .sc-content-$container container)
     *
     * @param string $container Optional. Container name to check for.
     *                          If not passed checks for all sc-content containers
     *
     * @return bool
     */
    public function hasContainer($container = '')
    {
        return in_array($container, $this->containerNames());
    }
    //</editor-fold>

    //<editor-fold desc="Menus related methods">

    /**
     * Returns array of all Menu objects filtered by name if parameter passed
     *
     * @param string|null $filter
     *
     * @return ElementList
     */
    public function menus($filter = null)
    {
        return $this->contentManager->listMenus($filter);
    }

    /**
     * Saves menus in page based on passed arguments
     *
     * @param string        $name
     * @param string        $template
     * @param array|null    $items
     * @param callable|null $itemProcess
     * @param string|null   $activeClass
     * @param callable|null $isActive
     *
     * @return array|null
     */
    public function saveMenus(
        $name,
        $template = Menu::DEFAULT_TEMPLATE,
        $items = null,
        $itemProcess = null,
        $activeClass = null,
        $isActive = null
    ) {
        $menus = $this->menus($name);
        $processedItems = [];
        foreach ($menus as $menu) {
            // If items are passed, set them
            if ($items !== null) {
                $menu->items(
                    $items,
                    $itemProcess
                );
            }
            $processedItems = $menu->items();
            $menu->setTemplate($template);
            if ($activeClass !== null) {
                $menu->setActiveClass($activeClass);
            }
            $menu->html($isActive);
        }

        return $processedItems;
    }
    //</editor-fold>

    //<editor-fold desc="Title and metadata manipulation">
    /**
     * Returns the page title (the title tag).
     *
     * @return string the current value of the title tag
     */
    public function getPageTitle()
    {
        $element = $this->query('title')->first();
        if ($element) {
            return $element->text();
        }

        return '';
    }

    /**
     * Sets the page title (the title tag).
     *
     * @param string $title Title to be set
     */
    public function setPageTitle($title)
    {
        $titleElement = $this->query('title')->first();
        if ($title === '') {
            // If empty value passed we need to remove title tag
            if ($titleElement) {
                $this->removeElement($titleElement);
            }
        } else {
            if ($titleElement) {
                $titleElement->text($title);
            } else {
                $titleElement = new Title($title);
                $head = $this->query('head');
                if ($head->count() > 0) {
                    /* @var Element $head */
                    $this->appendTo($head->first(), $titleElement);
                }
            }
        }
    }

    /**
     * Reads the page description meta tag.
     *
     * @return string current description text
     */
    public function getPageDescription()
    {
        $element = $this->query('meta[name="description"]')->first();
        if ($element) {
            return $element->getAttribute('content');
        }

        return '';
    }

    /**
     * Sets the page description meta tag with the given content.
     *
     * @param string $text Description to be set
     */
    public function setPageDescription($text)
    {
        $metaDescription = $this->query('meta[name="description"]')->first();
        if ($text === '') {
            // If empty value passed we need to remove title tag
            if ($metaDescription) {
                $this->removeElement($metaDescription);
            }
        } else {
            if ($metaDescription) {
                $metaDescription->setAttribute('content', $text);
            } else {
                // Try to insert meta description tag into head
                $metaDescription = new Meta('description', $text);
                $head = $this->query('head');
                if ($head->count() > 0) {
                    /* @var Element $head */
                    $this->appendTo($head->first(), $metaDescription);
                }
            }
        }
    }
    //</editor-fold>
}
