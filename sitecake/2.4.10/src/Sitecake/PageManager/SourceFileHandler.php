<?php

namespace Sitecake\PageManager;

use Cake\Event\Event;
use Sitecake\Content\DOM\Element\ContentContainer;
use Sitecake\Content\DOM\Query\Selector\ContentContainerSelector;
use Sitecake\Content\DOM\Query\Selector\ResourceFileLinkSelector;
use Sitecake\Content\DOM\Query\Selector\ResourceImageSelector;
use Sitecake\Resources\AbstractResourceHandler;
use Sitecake\Resources\Exception\ResourceNotFoundException;
use Sitecake\Resources\ResourceManager;
use Sitecake\Resources\SourceFile;
use Sitecake\Sitecake;

class SourceFileHandler extends AbstractResourceHandler
{
    protected $defaultConfig = [];

    /**
     * Default pages specified in app configuration
     *
     * @var array
     */
    protected $defaultPages = [];

    /**
     * Cached default pages keyed by directory name
     *
     * @var array
     */
    protected $defaultPagesByDir;

    /**
     * SourceFileHandler constructor.
     *
     * {@inheritdoc}
     */
    public function __construct(
        ResourceManager $resourceManager,
        $config = []
    ) {
        parent::__construct($resourceManager, $config);

        Sitecake::eventBus()->on('Site.onDraftCreate', [$this, 'initializeContainerMap']);

        // Read default pages from configuration
        $this->defaultPages = (array)Sitecake::getConfig('site.default_pages');
    }

    /**
     * {@inheritdoc}
     */
    public static function type()
    {
        return 'sourceFile';
    }

    /**
     * {@inheritdoc}
     */
    public function supports($path)
    {
        if(preg_match('/' . $this->getPathMatcher() . '/', $path) === 1) {
            return true;
        }

        $normalized = $this->normalizePath($path);

        return (bool)preg_match('/' . $this->getPathMatcher() . '/', $normalized);
    }

    /**
     * {@inheritdoc}
     */
    public function getPathMatcher()
    {
        $extensionsPattern = implode('|', array_map(function ($extensions) {
            return preg_quote($extensions, '/') . '$';
        }, (array)$this->getValidSourceFileExtensions()));

        return '^.*\.(' . $extensionsPattern . ')$';
    }

    /**
     * Returns default page path
     *
     * @param string $directory
     *
     * @return array|mixed|string
     */
    public function getDefaultPage($directory = '')
    {
        if (isset($this->defaultPagesByDir[$directory])) {
            return $this->defaultPagesByDir[$directory];
        }

        foreach ($this->defaultPages as $defaultPage) {
            $path = ($directory ? rtrim($directory, '/') . '/' : '') . $defaultPage;
            if ($this->resourceManager->resourceExists($path)) {
                return $this->defaultPagesByDir[$directory] = $path;
            }
        }

        throw new ResourceNotFoundException([
            'page' => 'Source file',
            'files' => '(' . implode(', ', $this->defaultPages) . ')']
        );
    }

    /**
     * {@inheritdoc}
     */
    public function implementedEvents()
    {
        return [
            'ResourceManager.onDraftRemove' => 'onDraftRemove'
        ];
    }

    /**
     * onResourceRemove callback
     *
     * @param Event  $event
     * @param string $path
     */
    public function onDraftRemove(Event $event, $path)
    {
        $sourcePath = $this->resourceManager->stripDraftPath($path);
        if (($containers = $this->resourceManager->getResourceMetadata($sourcePath, 'containers')) === null) {
            $sourceFile = new SourceFile($sourcePath, $this->resourceManager->read($path));
            $containers = $sourceFile->containerNames();
        }
        if ($containers) {
            $containersMetadata = Sitecake::cache()->get('containerMap', []);
            foreach ($containers as $container) {
                if (($index = array_search($sourcePath, $containersMetadata[$container])) !== false) {
                    array_splice($containersMetadata[$container], $index, 1);
                    if (empty($containersMetadata[$container])) {
                        unset($containersMetadata[$container]);
                    }
                }
            }
            Sitecake::cache()->save('containerMap', $containersMetadata);
        }
    }

    /**
     * Returns source file URL based on pages.use_default_page_name_in_url config var
     * If var set to true, method will strip default index page name from URL.
     *
     * @param $path
     *
     * @return string
     */
    public function pathToUrl($path)
    {
        if (Sitecake::getConfig('pages.use_default_page_name_in_url')) {
            return $path;
        }
        $urlParts = explode('/', $path);
        $filename = array_pop($urlParts);
        $pathnameDir = implode('/', $urlParts);
        if (in_array($filename, $this->defaultPages)) {
            if ($pathnameDir) {
                return $pathnameDir . '/';
            }

            return '';
        }

        return $path;
    }

    /**
     * Returns path based on passed path which is converted from url
     *
     * @param $path
     *
     * @return string
     */
    public function normalizePath($path)
    {
        if (empty($path)) {
            return $this->getDefaultPage();
        } else {
            if ($this->resourceManager->exists($path)) {
                if ($this->resourceManager->isDirectory($path)) {
                    $path = $this->getDefaultPage($path);
                }
            }
        }

        return $path;
    }

    /**
     * Returns array of source file extensions that should be considered when handling source files.
     * Extensions are collected based on 'site.default_pages' app configuration var
     *
     * @return array
     */
    protected function getValidSourceFileExtensions()
    {
        return array_map(function ($pageName) {
            $nameParts = explode('.', $pageName);

            return array_pop($nameParts);
        }, $this->defaultPages);
    }

    /**
     * onInitialize callback
     */
    public function initializeContainerMap()
    {
        Sitecake::cache()->save('containerMap', []);
    }

    /**
     * Returns whole container map or map for specific container name(s) if passed
     *
     * @param null|string|array $containers Container name
     *
     * @return array|null
     */
    public function getContainerMap($containers = null)
    {
        if (($containerMap = Sitecake::cache()->get('containerMap')) === null) {
            return null;
        }

        if ($containers === null) {
            return $containerMap;
        }

        $containers = (array)$containers;

        $return = [];

        foreach ($containers as $container) {
            if (isset($containerMap[$container])) {
                $return = array_merge($return, $containerMap[$container]);
            }
        }

        return array_unique($return);
    }

    /**
     * Creates new page based on passed details
     *
     * {@inheritdoc}
     * @param SourceFile $from
     */
    public function createDraft($path, $resource = null)
    {
        $fromSource = false;
        $filter = null;
        if ($resource !== null) {
            $sourceFile = new SourceFile((string)$resource, $path);
            $filter = ContentContainerSelector::GENERATED_CONTAINERS_FILTER;
            $fromSource = true;
        } else {
            $sourceFile = new SourceFile($this->resourceManager->read($path), $path);
        }

        // Process content containers
        $containers = $sourceFile->getContentContainers($filter);
        $containerMap = Sitecake::cache()->get('containerMap', []);
        $containerNames = [];
        foreach ($containers as $container) {
            /** @var ContentContainer $container */
            // Re-generate old container names
            $containerName = $container->isNamed() ?
                $container->getName() :
                $container->generateName($fromSource);
            $containerNames[] = $containerName;
            if (!isset($containerMap[$containerName])) {
                $containerMap[$containerName] = [$path];
            } else {
                $containerMap[$containerName][] = $path;
            }

            // If creating draft from another draft source file we need to duplicate linked resources
            if ($fromSource) {
                // Duplicate resources from unnamed containers
                $resourceFileLinkSelector = new ResourceFileLinkSelector();
                $sets = [];
                foreach ($container->getElementsByTagName('a') as $link) {
                    /** @var \DOMElement $link */
                    $href = $link->getAttribute('href');
                    if (preg_match($resourceFileLinkSelector->getMatcher(), $href)) {
                        /** @var array $resourceDetails */
                        $resourceDetails = $this->resourceManager->buildResourceUrl($href);
                        if (isset($sets[$resourceDetails['id']])) {
                            $id = $sets[$resourceDetails['id']];
                        } else {
                            $id = uniqid();
                            $sets[$resourceDetails['id']] = $id;
                        }
                        $newPath = $this->resourceManager->buildResourceUrl(
                            $resourceDetails['path'],
                            $resourceDetails['name'],
                            $id,
                            $resourceDetails['subid'],
                            $resourceDetails['ext']
                        );
                        $link->setAttribute('href', $newPath);
                        $this->resourceManager->createDraft(
                            $this->resourceManager->stripDraftPath($newPath),
                            $this->resourceManager->read($this->resourceManager->urlToPath($href, $path))
                        );
                    }
                }

                $resourceFileLinkSelector = new ResourceFileLinkSelector();
                foreach ($container->getElementsByTagName('img') as $image) {
                    /** @var \DOMElement $image */
                    $src = $image->getAttribute('src');
                    if (preg_match($resourceFileLinkSelector->getMatcher(), $src)) {
                        /** @var array $resourceDetails */
                        $resourceDetails = $this->resourceManager->resourceUrlInfo($src);
                        if (isset($sets[$resourceDetails['id']])) {
                            $id = $sets[$resourceDetails['id']];
                        } else {
                            $id = uniqid();
                            $sets[$resourceDetails['id']] = $id;
                        }
                        $newPath = $this->resourceManager->buildResourceUrl(
                            $resourceDetails['path'],
                            $resourceDetails['name'],
                            $id,
                            $resourceDetails['subid'],
                            $resourceDetails['ext']
                        );
                        $image->setAttribute('src', $newPath);
                        $this->resourceManager->createDraft(
                            $this->resourceManager->stripDraftPath($newPath),
                            $this->resourceManager->read($this->resourceManager->urlToPath($src, $path))
                        );
                    }
                    $srcSet = $image->getAttribute('srcset');
                    if (!empty($srcSet)) {
                        $paths = explode(',', $srcSet);
                        foreach ($paths as &$srcWidthPair) {
                            list($src, $width) = explode(' ', $srcWidthPair);
                            if (preg_match($resourceFileLinkSelector->getMatcher(), $src)) {
                                /** @var array $resourceDetails */
                                $resourceDetails = $this->resourceManager->resourceUrlInfo($src);
                                if (isset($sets[$resourceDetails['id']])) {
                                    $id = $sets[$resourceDetails['id']];
                                } else {
                                    $id = uniqid();
                                    $sets[$resourceDetails['id']] = $id;
                                }
                                $newPath = $this->resourceManager->buildResourceUrl(
                                    $resourceDetails['path'],
                                    $resourceDetails['name'],
                                    $id,
                                    $resourceDetails['subid'],
                                    $resourceDetails['ext']
                                );
                                $this->resourceManager->createDraft(
                                    $this->resourceManager->stripDraftPath($newPath),
                                    $this->resourceManager->read($this->resourceManager->urlToPath($src, $path))
                                );
                                $srcWidthPair = $src . ' ' . $width;
                            }
                        }
                        $image->setAttribute('srcset', implode(',', $paths));
                    }
                }
            }
        }
        Sitecake::cache()->save('containerMap', $containerMap);

        // Write container names for this source file to metadata
        $this->resourceManager->storeResourceMetadata($path, 'containers', $containerNames);

        return $sourceFile;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareForPublish($draftPath)
    {
        $path = $this->resourceManager->stripDraftPath($draftPath);
        $sourceFile = new SourceFile($this->resourceManager->read($draftPath), $path);

        $containers = $sourceFile->getContentContainers();
        foreach ($containers as $container) {
            /** @var ContentContainer $container */
            // Cleanup generated content container names if has generated name
            if (!$container->isNamed()) {
                $container->clearGeneratedName();
            }

            // Normalize resource image src and srcset attributes (strip draft paths)
            $draftBase = $this->resourceManager->base() . $this->resourceManager->draftBaseUrl();
            $resourceImageSelector = new ResourceImageSelector();
            $contentModified = false;
            foreach ($container->getElementsByTagName('img') as $image) {
                /** @var \DOMElement $image */
                $srcWidthPair = $image->getAttribute('src');
                if (preg_match($resourceImageSelector->getSourcePattern(), $srcWidthPair)) {
                    if (strpos($srcWidthPair, $draftBase) === 0) {
                        $srcWidthPair = (string)substr($srcWidthPair, strlen($draftBase));
                    }
                    $srcWidthPair = $this->resourceManager->pathToUrl($srcWidthPair, $path);
                    $image->setAttribute('src', $srcWidthPair);
                    $contentModified = true;
                }
                $srcSet = $image->getAttribute('srcset');
                if (!empty($srcSet)) {
                    $paths = explode(',', $srcSet);
                    $newPaths = [];
                    foreach ($paths as $srcWidthPair) {
                        list($src, $width) = explode(' ', $srcWidthPair);
                        if (preg_match($resourceImageSelector->getSourcePattern(), $src)) {
                            if (strpos($src, $draftBase) === 0) {
                                $src = (string)substr($src, strlen($draftBase));
                            }
                            $src = $this->resourceManager->pathToUrl($src, $path);
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

        return $sourceFile;
    }
}
