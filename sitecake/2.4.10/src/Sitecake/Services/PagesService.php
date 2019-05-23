<?php

namespace Sitecake\Services;

use Sitecake\Content\MenuManager;
use Sitecake\Content\PageRenderAwareTrait;
use Sitecake\Exception\FileNotFoundException;
use Sitecake\Exception\InvalidArgumentException;
use Sitecake\PageManager\Page;
use Sitecake\PageManager\PageManager;
use Sitecake\Resources\ResourceManager;
use Sitecake\Resources\SourceFile;
use Sitecake\Site;
use Sitecake\Sitecake;
use Sitecake\Util\Utils;
use Symfony\Component\HttpFoundation\Request;

class PagesService extends Service
{
    use PageRenderAwareTrait;

    const PAGE_QUERY = 'scpage';

    /**
     * @var PageManager
     */
    protected $pageManager;

    /**
     * @var MenuManager
     */
    protected $menuManager;

    /**
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * Sitecake paths
     *
     * @var array
     */
    protected $paths;

    /**
     * Sitecake entry point URL
     *
     * @var string
     */
    protected $entryPointFileName;

    /**
     * PagesService constructor.
     *
     * @param Site $site
     * @param          $alias
     * @param array $config
     */
    public function __construct(Site $site, $alias, array $config = [])
    {
        parent::__construct($site, $alias, $config);
        $this->pageManager = $this->getConfig('pageManager');
        $this->menuManager = $this->getConfig('menuManager');
        $this->resourceManager = $this->getConfig('resourceManager');
        $this->entryPointFileName = Sitecake::getConfig('entry_point_file_name');
        $this->paths = Sitecake::getPaths();
    }

    /**
     * Returns/saves page structure
     *
     * @param $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     * TODO: Need to separate save and read methods and update it in page manager client
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function pages($request)
    {
        $pageUpdates = $request->request->get('pages');
        $menuUpdates = $request->request->get('menus');
        if (!is_null($pageUpdates) || !is_null($menuUpdates)) {
            /**
             * Update pages
             * Go through all existing page files (from metadata) and compare it with received $pageUpdates
             *        - Create new pages (id not set tid set to source page)
             *        - Delete pages that could not be found in received $pageUpdates and we have it in page files
             *        - Update unnamed container names
             *        - Duplicate resources in unnamed containers
             *        - Update title and description metadata for all pages
             *        - Update site metadata
             */
            $pageUpdateMap = [];
            if (!empty($pageUpdates)) {
                $pageUpdates = json_decode($pageUpdates, true);
                $pagesMetadata = Sitecake::cache()->get('pages');
                $pathsForDeletion = array_keys($pagesMetadata);
                $metadata = [];
                $pages = [];
                foreach ($pageUpdates as $no => $pageDetails) {
                    // Get page path
                    $path = $pageDetails['path'];
                    $draftPath = $this->resourceManager->getDraftPath($path);

                    // Gather metadata for later update
                    $metadata[$path] = $pageDetails;

                    if (!isset($pageDetails['id'])) {
                        if (!isset($pageDetails['tid'])) {
                            throw new InvalidArgumentException(['name' => 'pages[' . $no . ']']);
                        }

                        // This is a new page, create it from source
                        $sourceFile = $this->pageManager->create($pageDetails['tid'], [
                            'title' => $pageDetails['title'],
                            'desc' => $pageDetails['desc'],
                            'path' => $path
                        ]);

                        $pages[$path] = [
                            'isNew' => true,
                            'path' => $path,
                            'page' => $sourceFile,
                        ];

                        $metadata[$path]['id'] = Utils::id();
                        unset($metadata[$path]['tid']);
                    } else {
                        // Find page path by ID in case it's changed
                        $originalPath = '';
                        foreach ($pagesMetadata as $pagePath => $details) {
                            if ($details['id'] == $pageDetails['id']) {
                                $originalPath = $pagePath;
                                break;
                            }
                        }

                        /* @var SourceFile $sourceFile */
                        if (!($sourceFile = new SourceFile($this->resourceManager->readDraft($originalPath), $path))) {
                            throw new FileNotFoundException([
                                'type' => 'Source Page',
                                'file' => $path
                            ], 401);
                        }

                        // If page path is changed and there is not re-created we need to delete old page
                        if ($path !== $originalPath) {
                            // Page path is updated. Save it for menu metadata update
                            $pageUpdateMap[$originalPath] = $path;
                            // If original path exist as map value for some other path that means it's re-created
                            if ((array_search($originalPath, $pathsForDeletion) === false) &&
                                !in_array($originalPath, $pageUpdateMap)) {
                                array_push($pathsForDeletion, $originalPath);
                            }
                            $pages[$path] = [
                                'isNew' => false,
                                'path' => $path,
                                'page' => $sourceFile,
                            ];
                        } elseif (isset($pagesMetadata[$path]) &&
                            !$this->isPageChanged($pagesMetadata[$path], $pageDetails)) {
                            unset($pathsForDeletion[array_search($path, $pathsForDeletion)]);
                            continue;
                        }

                        $sourceFile->setPageTitle($pageDetails['title']);
                        $sourceFile->setPageDescription($pageDetails['desc']);

                        $this->resourceManager->markPathDirty($draftPath);

                        if ($path === $originalPath) {
                            $this->resourceManager->write($draftPath, $sourceFile);
                            $this->resourceManager->saveLastModified($draftPath);
                        }
                    }

                    if (($index = array_search($path, $pathsForDeletion)) !== false) {
                        unset($pathsForDeletion[$index]);
                    }
                }

                // Remove deleted pages
                if (!empty($pathsForDeletion)) {
                    $pageUpdateMap = array_merge($pageUpdateMap, $pathsForDeletion);
                    foreach ($pathsForDeletion as $pathForDeletion) {
                        $pathForDeletion = $this->resourceManager->getDraftPath($pathForDeletion);
                        $this->resourceManager->delete($pathForDeletion);
                    }
                }

                $this->pageManager->updateSourceFiles($pages);

                // Save metadata for pages
                Sitecake::cache()->save('pages', $metadata);
            }

            // Update menus
            if (!empty($menuUpdates)) {
                $this->menuManager->updateMenus(json_decode($menuUpdates, true), $pageUpdateMap);
            }

            // Publish draft
            $this->site->publishDraft();

            $pages = [];
            foreach ($metadata as $path => $page) {
                $pages[] = array_merge($page, ['path' => $path]);
            }
        } else {
            $pages = $this->pageManager->listPages();
        }

        return $this->json($request, [
            'status' => 0,
            'pages' => $pages,
            'menus' => $this->menuManager->listMenus()
        ], 200);
    }

    protected function isPageChanged($existingPageData, $receivedPageData)
    {
        return $existingPageData['title'] !== $receivedPageData['title']
            || $existingPageData['desc'] !== $receivedPageData['desc'];
    }

    /**
     * Render service
     * Renders page passed in request in sitecake edit mode
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Exception
     */
    public function render(Request $request)
    {
        $path = $request->query->get(self::PAGE_QUERY) ?: '';

        $this->site->initialize();

        return $this->renderEditMode($path);
    }

    /**
     * Page path
     *
     * @param $path
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    protected function renderEditMode($path)
    {
        $page = $this->pageManager->getDraft($path);

        $this->preparePageForRender($page);

        $this->injectEditorCode($page);

        $content = $page->render();

        return $this->response($content);
    }

    /**
     * Adds needed sitecake scripts and styles to passed page
     *
     * @param Page $page
     *
     * @return void
     * TODO: Implement that enqueued styles and scripts are embeded into page in order they are added
     */
    protected function injectEditorCode(Page $page)
    {
        $page->enqueueStyle($this->paths['PAGEMANAGER_CSS_URL'])
            ->enqueueScript($this->paths['PAGEMANAGER_JS_URL'], [], ['data-cfasync' => 'false'])
            ->enqueueScript($this->paths['PAGEMANAGER_VENDORS_URL'], [], ['data-cfasync' => 'false'])
            ->enqueueScript($this->paths['EDITOR_EDIT_URL'], [], ['data-cfasync' => 'false'])
            ->enqueueScript($this->sitecakeGlobals(), ['inline' => true]);
    }

    /**
     * Returns sitecakeGlobals definition code
     *
     * @return string
     */
    protected function sitecakeGlobals()
    {
        return 'var sitecakeGlobals = {' .
            'editMode: true, ' .
            'serverVersionId: "2.4.8dev", ' .
            'phpVersion: "' . phpversion() . '@' . PHP_OS . '", ' .
            'serviceUrl: "' . $this->paths['SERVICE_URL'] . '", ' .
            'configUrl: "' . $this->paths['EDITOR_CONFIG_URL'] . '", ' .
            'draftPublished: ' . ($this->site->isPublished() ? 'true' : 'false') . ', ' .
            'entryPoint: "' . $this->entryPointFileName . '",' .
            'indexPageName: "' . $this->pageManager->getHomePageName() . '"' .
            '};';
    }
}
