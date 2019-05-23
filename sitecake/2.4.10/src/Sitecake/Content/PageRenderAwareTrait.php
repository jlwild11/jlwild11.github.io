<?php

namespace Sitecake\Content;

use Sitecake\Content\DOM\Element\Meta;
use Sitecake\Content\DOM\Query\Selector\ResourceImageSelector;
use Sitecake\PageManager\Page;
use Sitecake\PageManager\PageManager;
use Sitecake\Resources\ResourceManager;
use Sitecake\Sitecake;
use Sitecake\Util\Utils;

/**
 * Trait PageRenderAwareTrait
 *
 * @property ResourceManager $resourceManager
 * @property PageManager $pageManager
 *
 * @package Sitecake\Content
 */
trait PageRenderAwareTrait
{
    /**
     * + Prefixes resource URLs with draft base URL
     * + Adds 'application-name' meta tag with pageid data attribute
     * + Adds robots 'noindex,nofollow' meta tag
     * + Converts page links to 'edit mode' links
     * + Cache container contents for later comparison if indicated
     *
     * {@inheritdoc}
     */
    public function preparePageForRender(Page $page)
    {
        // Normalize script and css file URLs
        $this->normalizeScriptAndCssUrls($page);

        // Prefixes resource URLs with draft base URL
        $this->prefixResourceImageSrcAttributes($page);

        // Set Page ID meta tag stored in metadata
        $this->ensureMetaTag($page, 'application-name', 'sitecake', ['data-pageid' => $page->getID()]);

        // Add robots meta tag
        $this->ensureMetaTag($page, 'robots', 'noindex, nofollow');

        // Convert page links outside of editable containers to 'edit mode' links
        $this->convertPageLinks($page);
    }

    /**
     * Finds all script tags with src attributes and link css tags and updates URLs to site root relative URLs
     *
     * @param Page $page
     */
    public function normalizeScriptAndCssUrls(Page $page)
    {
        // Normalize css file links
        $cssLinks = $page->query('link[rel="stylesheet"]');
        foreach ($cssLinks as $css) {
            $href = $css->getAttribute('href');
            if (strpos($href, 'http') !== 0 && strpos($href, '//') !== 0) {
                $css->setAttribute(
                    'href',
                    $this->resourceManager->base() . $this->resourceManager->urlToPath($css->getAttribute('href'))
                );
            }
        }

        // Normalize script source attributes
        $scripts = $page->query('script[src$=".js"]');
        foreach ($scripts as $script) {
            $src = $script->getAttribute('src');
            if (strpos($src, 'http') !== 0 && strpos($src, '//') !== 0) {
                $script->setAttribute(
                    'src',
                    $this->resourceManager->base() . $this->resourceManager->urlToPath($script->getAttribute('src'))
                );
            }
        }
    }

    /**
     * Prefix all resource image source attributes on passed page with draft base URL
     * File resources doesn't need to be prefixed because links in content containers need to point to actual file
     *
     * @param Page $page Page
     */
    protected function prefixResourceImageSrcAttributes(Page $page)
    {
        $containers = $page->getContentContainers();
        $resourceImageSelector = new ResourceImageSelector();
        foreach ($containers as $container) {
            $contentModified = false;
            foreach ($container->getElementsByTagName('img') as $image) {
                /** @var \DOMElement $image */
                $src = $image->getAttribute('src');
                if (preg_match($resourceImageSelector->getSourcePattern(), $src)) {
                    $src = $this->resourceManager->base() .
                        $this->resourceManager->urlToPath($src);
                    $image->setAttribute('src', $src);
                    $contentModified = true;
                }
                $srcSet = $image->getAttribute('srcset');
                if (!empty($srcSet)) {
                    $paths = explode(',', $srcSet);
                    $newPaths = [];
                    foreach ($paths as $srcWidthPair) {
                        list($src, $width) = explode(' ', $srcWidthPair);
                        if (preg_match($resourceImageSelector->getSourcePattern(), $src)) {
                            $src = $this->resourceManager->base() .
                                $this->resourceManager->urlToPath($src);
                            $newPaths[] = $src . ' ' . $width;
                        }
                    }
                    if (count($newPaths) > 0) {
                        $image->setAttribute('srcset', implode(',', $newPaths));
                        $contentModified = true;
                    }
                }
            }
            $container->contentModified($contentModified);
        }
    }

    /**
     * Updates or creates if doesn't exist meta tag with passed name and passed content
     * in content of passed page. There is possibility to pass additional attributes
     *
     * @param Page   $page
     * @param string $name
     * @param string $content
     * @param array  $attributes
     *
     * @return Meta|Element
     */
    protected function ensureMetaTag(Page $page, $name, $content, $attributes = [])
    {
        // Check if meta tag already exist
        $metaExists = false;
        $existingAttributes = [];
        $metaTags = $page->query('meta[name="' . $name . '"]');
        if ($metaTags->count() > 0) {
            /* @var Element $meta */
            $meta = $metaTags->first();
            $metaExists = true;
            // Remove all existing attributes except 'name' attribute
            $existingAttributes = $meta->getAttributes();
            foreach ($existingAttributes as $attr => $value) {
                if ($attr !== 'name') {
                    $meta->removeAttribute((string)$attr);
                }
            }
            // set content attribute
            $meta->setAttribute('content', $content);
        } else {
            $meta = new Meta($name, $content);
        }
        $attributes = $existingAttributes + $attributes;
        unset($attributes['name'], $attributes['content']);
        // Set passed attributes on meta tag
        foreach ($attributes as $attr => $value) {
            $meta->setAttribute($attr, $value);
        }

        // If meta tag doesn't exists we need to add it. Otherwise just need to set data-pageid attr if not set
        if (!$metaExists) {
            $head = $page->query('head');
            if ($head->count() === 0) {
                throw new \LogicException('HEAD tag is missing in passed fragment');
            }
            $page->appendTo($head->first(), $meta);
        }

        return $meta;
    }

    /**
     * Convert all links outside of editable containers to 'edit mode' links
     *
     * @param Page $page Page instance
     */
    protected function convertPageLinks(Page $page)
    {
        $allLinks = $page->query('a');
        foreach ($allLinks as $linkElement) {
            if ($page->isEditableElement($linkElement)) {
                continue;
            }
            $href = $linkElement->getAttribute('href');
            if (Utils::isLocalFileUrl($href)) {
                // Preserve query string in link if present
                if (strpos($href, '?') !== false) {
                    list($href, $query) = explode('?', $href);
                }

                // Strip anchor part of URL and trim left dot if present
                $path = $this->resourceManager->urlToPath($href);

                if (!empty($path) && !$this->pageManager->isPage($path)) {
                    continue;
                }

                $href = Sitecake::getConfig('entry_point_file_name') . '?scpage=' .
                    $path . (isset($query) ? '&' . $query : '');
                $linkElement->setAttribute('href', $href);
            }
        }
    }
}
