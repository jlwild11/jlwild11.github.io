<?php

namespace Sitecake\PageManager;

use Cake\Event\Event;
use Sitecake\Cache;
use Sitecake\Exception\FileNotFoundException;
use Sitecake\Filesystem\Filesystem;
use Sitecake\Resources\ResourceManager;
use Sitecake\Resources\SourceFile;
use Sitecake\Sitecake;
use Sitecake\Util\Utils;

class PageManager implements PageManagerInterface
{
    const SC_PAGES_EXCLUSION_CHARACTER = '!';

    const SC_PAGES_FILENAME = '.scpages';

    /**
     * Default configuration
     *
     * @var array
     */
    protected $defaultConfig = [];

    /**
     * List of page file paths
     *
     * @var array
     */
    protected $pagePaths;

    /**
     * Array of paths from .scpages paths
     *
     * @var array
     */
    protected $scPages;

    /**
     * Entry point filename.
     * Used when converting links on page to 'edit mode' links
     *
     * @var string
     */
    protected $entryPointFilename = 'sitecake.php';

    /**
     * Sitecake config
     *
     * @var array
     */
    protected $config;

    /**
     * Resource manager
     *
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * Cache handler
     *
     * @var Cache
     */
    protected $cache;

    /**
     * @var TemplateManagerInterface
     */
    protected $templateManager;

    /**
     * @var SourceFileHandler
     */
    protected $sourceFileHandler;

    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * PageManager constructor.
     *
     * @param ResourceManager $resourceManager
     * @param TemplateManagerInterface $templateManager
     * @param Cache $cache
     * @param Filesystem $fs
     *
     * @throws \Exception
     */
    public function __construct(
        ResourceManager $resourceManager,
        TemplateManagerInterface $templateManager,
        Cache $cache,
        Filesystem $fs
    ) {
        $this->templateManager = $templateManager;
        // Initialize resource manager and register SourceFile handler
        $this->resourceManager = $resourceManager;
        $this->resourceManager->ignore(self::SC_PAGES_FILENAME);
        $this->sourceFileHandler = new SourceFileHandler(
            $this->resourceManager
        );
        $this->resourceManager->registerHandler($this->sourceFileHandler);
        $this->resourceManager->getEventManager()->on('ResourceManager.onDraftCreate', [$this, 'onDraftCreate']);
        $this->resourceManager->getEventManager()->on('ResourceManager.onDraftPublish', [$this, 'onDraftPublish']);
        $this->resourceManager->getEventManager()->on('ResourceManager.onResourceRemove', [$this, 'onResourceRemove']);

        // Initialize filesystem
        $this->fs = $fs;

        // Initialize metadata handler
        $this->cache = $cache;

        // Read entry point filename from configuration
        $this->entryPointFilename = Sitecake::getConfig('entry_point_file_name');
    }

    /**
     * Returns home page filename
     *
     * @return array|mixed|string
     */
    public function getHomePageName()
    {
        return $this->sourceFileHandler->getDefaultPage();
    }

    /**
     * Identifier is page path for requested page
     *
     * {@inheritdoc}
     */
    public function getPage($identifier = null)
    {
        if (!empty($identifier)) {
            if ($this->resourceManager->isDirectory($identifier)) {
                $identifier = $this->sourceFileHandler->getDefaultPage($identifier);
            }
        } else {
            $identifier = $this->sourceFileHandler->getDefaultPage();
        }
        $page = new Page(new SourceFile($this->resourceManager->read($identifier), $identifier));

        return $page;
    }

    /**
     * Identifier is page path for requested draft page
     *
     * {@inheritdoc}
     */
    public function getDraft($identifier = '')
    {
        if (!empty($identifier)) {
            if ($this->resourceManager->isDirectory($identifier)) {
                $identifier = $this->sourceFileHandler->getDefaultPage($identifier);
            }
        } else {
            $identifier = $this->sourceFileHandler->getDefaultPage();
        }

        if ($this->resourceManager->draftExists($identifier)) {
            $draftPath = $this->resourceManager->getDraftPath($identifier);

            $pagesInfo = $this->cache->get('pages');
            $pageID = !isset($pagesInfo[$identifier]['id']) ? '' : $pagesInfo[$identifier]['id'];
            if (empty($pageID)) {
                $pageID = Utils::id();
                $pagesInfo[$identifier]['id'] = $pageID;
                $this->cache->save('pages', $pagesInfo);
            }

            // We need to move execution dir to draft dir because of includes in .php files
            $currentWorkingDir = getcwd();

            // Check if we need to change execution directory
            $executionDirectory = '';
            if ($dir = implode('/', array_slice(explode('/', $identifier), 0, -1))) {
                $executionDirectory = $dir;
            }

            // Move execution to directory where requested page is because of php includes
            chdir($this->resourceManager->getDraftPath($executionDirectory, true));

            $page = new Page(
                new SourceFile($this->resourceManager->read($draftPath), $identifier),
                $pageID
            );

            // Turn execution back to root dir
            chdir($currentWorkingDir);

            return $page;
        } else {
            throw new FileNotFoundException([
                'type' => 'Draft Page',
                'files' => $identifier
            ], 401);
        }
    }

    /**
     * Creates new page based on passed details
     *
     * @param SourceFile|TemplateInterface|string $template
     * @param array $data
     *
     * @return SourceFile|bool
     */
    public function create($template, $data)
    {
        if (!($template instanceof TemplateInterface)) {
            /* @var SourceFile $template */
            $template = $this->templateManager->getTemplate($template);
        }
        /* @var SourceFile $sourceFile */
        if ($sourceFile = $this->resourceManager->createDraft($data['path'], $template->getSource())) {
            if (isset($data['title'])) {
                $sourceFile->setPageTitle($data['title']);
            }
            if (isset($data['desc'])) {
                $sourceFile->setPageDescription($data['desc']);
            }

            return $sourceFile;
        }

        return false;
    }

    /**
     * updates source file containers with passed content
     *
     * @param mixed $identifier
     * @param array $content
     */
    public function saveDraft($identifier, $content = [])
    {
        // Get pages that contains changed containers
        $sourceFiles = $this->getSourceFilesWithContainers(array_keys($content));
        if (!empty($sourceFiles)) {
            foreach ($sourceFiles as $draftPath => $sourceFile) {
                /* @var SourceFile $sourceFile */
                // Get container names for certain page
                $pageContainers = $this->resourceManager->getResourceMetadata($sourceFile->getPath(), 'containers');
                // Filter only container contents that are actually contained inside certain source file
                $containers = [];
                foreach ($content as $containerName => $containerContent) {
                    if (in_array($containerName, $pageContainers)) {
                        $containers[$containerName] = $containerContent;
                    }
                }
                $sourceFile->setContainerContent($containers);
                $this->resourceManager->update($draftPath, (string)$sourceFile);
            }
        }
    }

    /**
     * Parses .scpages file and returns array of paths from it
     *
     * @throws \League\Flysystem\FileNotFoundException
     */
    private function __parseScPagesFile()
    {
        if ($this->fs->has(self::SC_PAGES_FILENAME)) {
            $scPages = $this->fs->read(self::SC_PAGES_FILENAME);

            $this->scPages = [];
            if (!empty($scPages)) {
                $this->scPages = array_values(array_filter(preg_split('/\R/', $scPages)));
            }
        } else {
            $this->scPages = false;
        }
    }

    /**
     * Add passed path to .scpages paths. If second parameter is true, .scpages file will be persisted
     *
     * @param string $path
     * @param bool $persist
     */
    protected function addPathToScPages($path, $persist = false)
    {
        if ($this->scPages === null) {
            $this->__parseScPagesFile();
        }

        if ($this->scPages === false) {
            $this->scPages = [];
        }

        $parts = explode('/', $path);
        array_pop($parts);
        $inScPages = false;
        foreach ($parts as $no => $part) {
            $partial = $part . '/';
            if (array_search($partial, $this->scPages) !== false) {
                $inScPages = true;
                break;
            }
        }

        if (!$inScPages) {
            $this->scPages[] = $path;
        }

        if ($persist) {
            $this->fs->put(self::SC_PAGES_FILENAME, implode("\n", $this->scPages));
        }
    }

    /**
     * Return array of pages
     *
     * @return array
     */
    public function listPages()
    {
        $metadata = $this->cache->get('pages', []);
        $pageFilePaths = $this->loadPageFilePaths();

        $pages = [];
        foreach ($pageFilePaths as $no => $path) {
            if (isset($metadata[$path])) {
                $pages[] = array_merge($metadata[$path], ['path' => $path]);
            }
        }

        return $pages;
    }

    /**
     * Returns array of page details that contains passed container.
     * Each page detail is consisted of Page object and path to that specific page.
     *
     * @param string|array $containers Container name
     *
     * @return array
     * @throws \League\Flysystem\FileNotFoundException
     */
    protected function getSourceFilesWithContainers($containers)
    {
        $sourceFiles = [];
        // If 'containerMap' metadata isn't initialized, we need to initialize it
        if (($paths = $this->sourceFileHandler->getContainerMap($containers)) === null) {
            $sourceFilePaths = $this->resourceManager->listResources(SourceFileHandler::type());
            foreach ($sourceFilePaths as $path) {
                $draftPath = $this->resourceManager->getDraftPath($paths);
                $sourceFile = new SourceFile($this->fs->read($draftPath), $path);
                $containers = (array)$containers;
                foreach ($containers as $container) {
                    if ($sourceFile->hasContainer($container)) {
                        $sourceFiles[$draftPath] = $sourceFile;
                        break;
                    }
                }
            }
        } else {
            foreach ($paths as $path) {
                $draftPath = $this->resourceManager->getDraftPath($path);
                $sourceFiles[$draftPath] = new SourceFile($this->fs->read($draftPath), $path);
            }
        }

        return $sourceFiles;
    }

    /**
     * Updates content for passed source files and adds new subdirectory pages to .scpages file if any
     *
     * @param $pages
     * @param $pagesMetadata
     *
     *
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function updateSourceFiles($pages)
    {
        if ($this->scPages === null) {
            $this->__parseScPagesFile();
        }
        $initialScPagesCount = count($this->scPages);
        // Update page files
        foreach ($pages as $page) {
            $path = $this->resourceManager->getDraftPath($page['path']);

            $this->resourceManager->write($path, $page['page']);
            if ($page['isNew']) {
                // If new page is in subdirectory we need to add it to .scpages file for it to be visible
                if (strpos($page['path'], '/') !== false
                    && array_search($page['path'], $this->scPages) === false
                ) {
                    array_push($this->scPages, $page['path']);
                }
                array_push($this->pagePaths, $page['path']);
            }
            // Update last modified time in metadata
            $this->resourceManager->saveLastModified($path);
        }

        // If there are new paths for .scpages file, we need to write it
        if ($initialScPagesCount < count($this->scPages)) {
            $this->fs->put('.scpages', implode("\n", $this->scPages));
        }
    }

    /**
     * onDraftCreate callback.
     *
     * @param Event $event
     * @param       $resource
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function onDraftCreate(Event $event, $resource)
    {
        if ($resource instanceof SourceFile) {
            $path = $resource->getPath();
            // Cache page metadata
            $pagesMetadata = $this->cache->get('pages', []);
            if ($this->isPage($path) && !isset($pagesMetadata[$path])) {
                $id = Utils::id();
                $pagesMetadata[$path] = [
                    // Set page id
                    'id' => $id,
                    // Set page title
                    'title' => (string)$resource->getPageTitle(),
                    // Set page description
                    'desc' => (string)$resource->getPageDescription()
                ];
            }
            $this->cache->save('pages', $pagesMetadata);
        }
    }

    /**
     * onDraftPublish callback.
     *
     * @param Event $event
     * @param string|SourceFile $resource
     */
    public function onDraftPublish(Event $event, $resource)
    {
        if ($resource instanceof SourceFile) {
            // If new page is in subdirectory we need to add it to .scpages file for it to be visible
            $path = $resource->getPath();
            if (!$this->resourceManager->resourceExists($path) && strpos($resource->getPath(), '/') !== false) {
                $this->addPathToScPages($resource->getPath());
            }
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
        if ($this->sourceFileHandler->supports($path)) {
            // Cache page metadata
            $pagesMetadata = $this->cache->get('pages', []);
            // If .scpages file is changed pages won't match so we need to check and add if needed
            if ($this->isPage($path) && !isset($pagesMetadata[$path])) {
                unset($pagesMetadata[$path]);
            }
            $this->cache->save('pages', $pagesMetadata);
        }
    }

    //<editor-fold desc="Source files related methods">

    /**
     * Returns a list of paths for source files
     *
     * @return array
     */
    protected function findSourceFiles()
    {
        if (isset($this->sourceFiles)) {
            return $this->sourceFiles;
        }

        return $this->sourceFiles = $this->resourceManager->listResources(SourceFileHandler::type());
    }

    /**
     * Returns list of paths of Page files.
     *
     * Files that are considered as Page files are by default all files with valid extensions from root directory
     * and all files stated in .scpages file if it's present.
     * Files from root directory that shouldn't be considered as Page files can be filtered out
     * by stating them inside .scpages prefixed with exclamation mark (!)
     * If directory is stated in .scpages file all files from that directory are considered as Page files
     *
     * @return array
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function loadPageFilePaths()
    {
        if ($this->pagePaths) {
            return $this->pagePaths;
        }

        // List all pages source files
        $sourceFiles = $this->findSourceFiles();

        $pattern = '[^\/]+';
        $includePatterns = [];
        $ignorePatterns = [];

        // If .scpages file present we need to add page files stated inside and filter out ones that starts with !
        if ($this->scPages === null) {
            $this->__parseScPagesFile();
        }

        if ($this->scPages !== false) {
            // Load page life paths from .scpages
            $scPagePaths = array_filter(preg_split('/\R/', $this->fs->read(self::SC_PAGES_FILENAME)));

            $ignores = array_filter($scPagePaths, function ($path) {
                return substr($path, 0, 1) === self::SC_PAGES_EXCLUSION_CHARACTER;
            });

            $includePatterns = array_map(function ($path) {
                return preg_replace(['/\*/', '/\/$/'], ['.*', '/.*'], preg_quote($path, '/'));
            }, array_diff($scPagePaths, $ignores));

            // Find paths that should be ignored
            $ignorePatterns = array_map(function ($path) {
                return preg_replace(['/\*/', '/\/$/'], ['.*', '/.*'], preg_quote($path, '/'));
            }, preg_replace(
                    '/' . preg_quote(self::SC_PAGES_EXCLUSION_CHARACTER) . '/',
                    '',
                    $ignores
                )
            );
        }

        if (!empty($includePatterns)) {
            $pattern .= '|' . implode('|', $includePatterns);
        }
        $sourceFiles = preg_grep('/^(' . $pattern . ')$/', $sourceFiles);

        // Do exclusion
        if (!empty($ignorePatterns)) {
            $sourceFiles = preg_grep('/^((?!' . implode('|', $ignorePatterns) . ').*)$/', $sourceFiles);
        }

        return $this->pagePaths = array_values($sourceFiles);
    }

    /**
     * Returns whether passed path is page file or not
     *
     * @param string $path
     *
     * @return bool
     */
    public function isPage($path)
    {
        if (!isset($this->pagePaths)) {
            $this->loadPageFilePaths();
        }

        return in_array($path, $this->pagePaths);
    }
    //</editor-fold>
}
