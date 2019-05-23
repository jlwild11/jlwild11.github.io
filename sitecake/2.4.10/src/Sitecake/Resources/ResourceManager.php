<?php

namespace Sitecake\Resources;

use Cake\Event\Event;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use League\Flysystem\Directory;
use League\Flysystem\File;
use League\Flysystem\Util;
use Sitecake\Cache;
use Sitecake\Filesystem\Filesystem;
use Sitecake\Resources\Exception\InvalidResourceHandlerException;
use Sitecake\Sitecake;

/**
 * Class ResourceManager
 *
 * @package Sitecake\Resources
 */
class ResourceManager implements EventDispatcherInterface
{
    use EventDispatcherTrait;

    const SC_IGNORE_FILENAME = '.scignore';
    const DRAFT_MARKER_FILENAME = 'draft.mkr';
    const DRAFT_DIRTY_FILENAME = 'draft.drt';

    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * Cache instance
     *
     * @var Cache
     */
    protected $cache;

    /**
     * Sitecake paths
     *
     * @var array
     */
    protected $paths;

    /**
     * Stores cached resources metadata for faster access and manipulation
     *
     * @var array
     */
    protected $resources = [];

    /**
     * SITE_ROOT relative path to 'draft' dir
     *
     * @var string
     */
    protected $draftPath;

    /**
     * List of ignored files (read from .scignore)
     *
     * @var array
     */
    protected $ignores;

    /**
     * @var array List of source file paths
     */
    protected $sourceFiles;

    /**
     * @var ResourceHandlerRegistry
     */
    protected $handlersRegistry;

    /**
     * Indicates whether draft has been initialized or not
     *
     * @var bool
     */
    protected $draftInitialized = false;

    /**
     * PageManager constructor.
     *
     * @param Filesystem $fs
     * @param Cache      $metadata
     * @param array      $ignores
     *
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function __construct(
        Filesystem $fs,
        Cache $metadata,
        array $ignores = []
    ) {
        $this->handlersRegistry = new ResourceHandlerRegistry($this);
        $this->fs = $fs;
        $this->cache = $metadata;
        $this->paths = Sitecake::getPaths();

        $this->ensureDirectoryStructure();

        $this->ignores = $ignores;
        $this->setIgnores();
    }

    /**
     * Ensure directory structure needed by handlers if necessary
     */
    protected function ensureDirectoryStructure()
    {
        // check/create sitecake-temp/<random-working-dir>/draft
        try {
            $draftPath = $this->fs->ensureDir($this->paths['SITECAKE_WORKING_DIR'] . '/draft');
            if ($draftPath === false) {
                throw new \LogicException('Could not ensure that the directory '
                    . $this->paths['SITECAKE_WORKING_DIR'] . '/draft is present and writable.');
            }
            $this->draftPath = $draftPath;
        } catch (\RuntimeException $e) {
            throw new \LogicException('Could not ensure that the directory '
                . $this->paths['SITECAKE_WORKING_DIR'] . '/draft is present and writable.');
        }

        // Ensure directory structure needed by resource handlers if necessary
        foreach ($this->handlersRegistry->loaded() as $handler) {
            $handler = $this->handlersRegistry->get($handler);
            if (($paths = $handler->requiredPaths($draftPath)) !== null) {
                $paths = (array)$paths;
                foreach ($paths as $path) {
                    try {
                        $path = $this->fs->ensureDir($path);
                        if ($draftPath === false) {
                            throw new \LogicException('Could not ensure that the directory '
                                . $path . ' is present and writable.');
                        }
                    } catch (\RuntimeException $e) {
                        throw new \LogicException('Could not ensure that the directory '
                            . $path . ' is present and writable.');
                    }
                }
            }
        }
    }

    /**
     * Builds ignore resources list
     *
     * @throws \League\Flysystem\FileNotFoundException
     */
    protected function setIgnores()
    {
        if ($this->fs->has(self::SC_IGNORE_FILENAME)) {
            $scIgnores = $this->fs->read(self::SC_IGNORE_FILENAME);

            if (!empty($scIgnores)) {
                $this->ignores = preg_split('/\R/', $this->fs->read(self::SC_IGNORE_FILENAME));
            }
        }
        $this->ignores = array_filter(array_merge($this->ignores, [
            self::SC_IGNORE_FILENAME,
            self::DRAFT_MARKER_FILENAME,
            self::DRAFT_DIRTY_FILENAME,
            Sitecake::getConfig('entry_point_file_name'),
            basename($this->paths['SITECAKE_DIR']) . '/'
        ]));

        $listeners = Sitecake::eventBus()->listeners('Sitecake.buildIgnoreList');
        foreach ($listeners as $listener) {
            if ($ignores = $listener(new Event('Sitecake.buildIgnoreList'))) {
                $this->ignores = array_merge($this->ignores, (array)$ignores);
            }
        }
    }

    /**
     * Adds passed resource(s) to ignore list
     *
     * @param array|string $ignores
     *
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function ignore($ignores = [])
    {
        $this->ignores = array_merge($this->ignores, (array)$ignores);

        if (!empty($this->resources)) {
            foreach ((array)$ignores as $path) {
                unset($this->resources[$path]);
                $this->cache->save('files', $this->resources);
            }
        }

        return $this->ignores;
    }

    //<editor-fold desc="Resource handler related methods">

    /**
     * Registers resource handler under passed name and configuration
     *
     * @param string $handler Class name or alias
     * @param array  $config  Configuration to be passed into handler constructor
     *
     * @return ResourceHandlerInterface
     * @throws \Exception
     */
    public function registerHandler($handler, $config = [])
    {
        if ($handler instanceof ResourceHandlerInterface) {
            $this->handlersRegistry->set($this->handlersRegistry->getAlias(get_class($handler)), $handler);
        } else {
            if (!isset($config['className'])) {
                $config['className'] = $handler;
                $handler = $this->handlersRegistry->getAlias($handler);
            }

            $handler = $this->handlersRegistry->load($handler, $config);
        }

        return $handler;
    }

    /**
     * Returns whether any of registered handlers supports passed resource
     *
     * @param string $resource Resource file info
     *
     * @return bool
     */
    public function hasSupportingHandler($resource)
    {
        foreach ($this->handlersRegistry->loaded() as $handler) {
            $handler = $this->handlersRegistry->get($handler);
            if ($handler->supports($resource)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns first matching handler that supports passed resource
     *
     * @param string $path Resource path
     *
     * @return ResourceHandlerInterface|null
     */
    public function getSupportingHandler($path)
    {
        foreach ($this->handlersRegistry->loaded() as $handler) {
            $handler = $this->handlersRegistry->get($handler);
            if ($handler->supports($path)) {
                return $handler;
            }
        }

        return null;
    }
    //</editor-fold>

    //<editor-fold desc="Resource manipulation methods">

    /**
     * Returns whether passed path exists on filesystem
     *
     * @param string $path
     *
     * @return bool
     */
    public function exists($path)
    {
        return $this->fs->has($path);
    }

    /**
     * Returns whether passed resource exist or not.
     *
     * @param string $path
     *
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function resourceExists($path)
    {
        return $this->isResource($path) && $this->exists($path);
    }

    /**
     * Checks if passed path is supported resource path
     *
     * @param string $path Public or draft resource path
     *
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function isResource($path)
    {
        return in_array($path, $this->listResources());
    }

    /**
     * Returns whether draft version of passed resource exist or not
     *
     * @param string $path
     *
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function draftExists($path)
    {
        return $this->isResource($path) && $this->exists($this->getDraftPath($path));
    }

    /**
     * Returns whether passed resource is draft or not
     *
     * @param string $path
     *
     * @return bool
     */
    public function isDraftResourcePath($path)
    {
        return strpos($path, $this->draftBaseUrl()) === 0;
    }

    /**
     * Returns whether passed resource is directory
     *
     * @param string $path
     *
     * @return bool
     */
    public function isDirectory($path)
    {
        return $this->fs->has($path) && $this->fs->get($path) instanceof Directory;
    }

    /**
     * Returns whether passed resource is file.
     *
     * @param string $path
     *
     * @return bool
     */
    public function isFile($path)
    {
        return $this->fs->has($path) && $this->fs->get($path) instanceof File;
    }

    /**
     * Returns resource on passed path.
     *
     * @param string|resource $path
     *
     * @return false|string
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function read($path)
    {
        if ($this->exists($path)) {
            return $this->fs->read($path);
        }

        return false;
    }

    /**
     * Returns draft resource on passed path.
     *
     * @param string|resource $path
     *
     * @return false|string
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function readDraft($path)
    {
        $path = $this->getDraftPath($path);
        if ($this->exists($path)) {
            return $this->fs->read($path);
        }

        return false;
    }

    /**
     * Writes resource under passed path. If resource already exists on passed path, it will be overwritten.
     *
     * @param string                            $path
     * @param string|resource|ResourceInterface $resource
     *
     * @return bool
     */
    public function write($path, $resource)
    {
        if (is_resource($resource)) {
            return $this->fs->putStream($path, $resource, ['disable_asserts' => false]);
        } else {
            return $this->fs->put($path, (string)$resource, ['disable_asserts' => false]);
        }
    }

    /**
     * Creates draft for resource under passed path.
     * If second parameter is passed it is considered as source.
     *
     * @param string                                 $path
     * @param string|resource|ResourceInterface|null $resource
     *
     * @return string|bool|ResourceInterface
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function createDraft($path, $resource = null)
    {
        $fromSource = $resource !== null;
        if ($this->resourceExists($path) || $resource !== null) {
            if (($handler = $this->getSupportingHandler($path)) !== null) {
                if (!($resource = $handler->createDraft($path, $resource))) {
                    return false;
                }
            } else {
                $resource = $this->fs->read($path);
            }

            $draftPath = $this->getDraftPath($path);

            if (!$this->write($draftPath, $resource)) {
                return false;
            }

            if ($resource instanceof ResourceInterface) {
                $resource->setPath($path);
            }

            $this->saveLastModified($draftPath);
            if ($fromSource) {
                $this->markPathDirty($draftPath);
            }

            // Fire onDraftCreate event
            $event = $this->dispatchEvent('ResourceManager.onDraftCreate', [
                'resource' => ($resource instanceof ResourceInterface ? $resource : $draftPath)
            ]);

            // If ResourceInterface is returned from callback we need to update resource content
            if (($result = $event->getResult()) !== null) {
                $this->update($path, (string)$result);

                $this->saveLastModified($draftPath);
            }

            if ($resource instanceof ResourceInterface) {
                return $resource;
            }

            return $draftPath;
        }

        return false;
    }

    /**
     * Publishes resource on passed draft path
     *
     * @param string $draftPath
     *
     * @return array|bool|false|string|ResourceInterface
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function publish($draftPath)
    {
        if ($this->exists($draftPath)) {
            $path = $this->stripDraftPath($draftPath);
            if (($handler = $this->getSupportingHandler($path)) !== null) {
                if (!($resource = $handler->prepareForPublish($draftPath))) {
                    return false;
                }
            } else {
                $resource = $this->fs->read($path);
            }

            if (!$this->write($path, $resource)) {
                return false;
            }
            $this->saveLastModified($path);

            if ($resource instanceof ResourceInterface) {
                $resource->setPath($path);
            }

            // Fire onDraftCreate event
            $event = $this->dispatchEvent('ResourceManager.onDraftPublish', [
                'resource' => ($resource instanceof ResourceInterface ? $resource : $draftPath)
            ]);

            // If ResourceInterface is returned from callback we need to update resource content
            if (($result = $event->getResult()) !== null) {
                $this->update($path, (string)$result);

                $this->saveLastModified($path);
            }

            if ($resource instanceof ResourceInterface) {
                return $resource;
            }

            return $path;
        }

        return false;
    }

    /**
     * Updates resource on passed path
     *
     * @param string                            $path
     * @param string|resource|ResourceInterface $resource
     *
     * @return bool
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function update($path, $resource)
    {
        if ($this->fs->has($path)) {
            if (is_resource($resource)) {
                $updated = $this->fs->updateStream($path, $resource);
            } else {
                $updated = $this->fs->update($path, (string)$resource);
            }

            if ($updated) {
                $this->saveLastModified($path);
                if ($this->isDraftResourcePath($path)) {
                    $this->markPathDirty($path);
                }
            }
        }

        return false;
    }

    /**
     * Deletes resource on passed path.
     *
     * @param string $path
     *
     * @return false|string
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function delete($path)
    {
        if ($this->fs->has($path)) {
            if ($this->fs->delete($path)) {
                if ($this->isDraftResourcePath($path)) {
                    $this->markPathDirty($path);
                    $this->dispatchEvent('ResourceManager.onDraftRemove', [
                        'path' => $path
                    ]);
                } else {
                    $this->dispatchEvent('ResourceManager.onResourceRemove', [
                        'path' => $path
                    ]);
                    $this->removeResourceFromMetadata($path);
                }
            }
        }

        return true;
    }

    /**
     * Returns all files on filesystem from passed directory recursively that needs to be handled by sitecake
     *
     * @param string $directory Directory to search resources in
     *
     * @return array List of found paths
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function __findResources($directory = '')
    {
        // List first level files/dirs for passed directory
        $firstLevel = $this->fs->listWith(['path', 'type', 'basename', 'timestamp'], $directory);

        $files = [];

        $ignorePattern = '/^(?!' .
            implode('|',
                array_map(function ($path) {
                    return preg_quote($path, '/') . '$';
                }, $this->ignores)
            ) . ').+$/';

        $metadata = $this->cache->get('files', []);

        foreach ($firstLevel as $file) {
            // Filter out files and dirs from first level that are in ignores
            if (($file['type'] == 'dir' && !in_array($file['path'] . '/', $this->ignores))
                || ($file['type'] == 'file' && !in_array($file['basename'], $this->ignores))
            ) {
                if ($file['type'] == 'dir') {
                    $subDirPaths = $this->fs->listWith(
                        ['path', 'type', 'timestamp', 'basename'],
                        $file['path'],
                        true
                    );

                    foreach ($subDirPaths as $path) {
                        if ($path['type'] == 'dir') {
                            continue;
                        }
                        // Filter out files and dirs that are in ignores and check for support
                        if (preg_match($ignorePattern, $path['path']) && $this->hasSupportingHandler($path['path'])) {
                            if (isset($metadata[$path['path']][0])) {
                                $files[$path['path']] = $metadata[$path['path']];
                                $files[$path['path']][0] = $path['timestamp'];
                            } else {
                                $files[$path['path']] = [$path['timestamp']];
                            }
                        }
                    }
                } else {
                    if ($this->hasSupportingHandler($file['path'])) {
                        if (isset($metadata[$file['path']][0])) {
                            $files[$file['path']] = $metadata[$file['path']];
                            $files[$file['path']][0] = $file['timestamp'];
                        } else {
                            $files[$file['path']] = [$file['timestamp']];
                        }
                    }
                }
            }
        }

        return $files;
    }

    /**
     * Filters passed resource paths based on passed resource type
     *
     * @param string $type
     * @param array  $paths
     *
     * @return array
     */
    protected function filterResourcePaths($type, $paths)
    {
        if (!$this->handlersRegistry->has($type)) {
            throw new InvalidResourceHandlerException(['handler' => $type]);
        }

        $handler = $this->handlersRegistry->get($type);

        return array_values(preg_grep('/' . $handler->getPathMatcher() . '/', $paths));
    }

    /**
     * Finds all files on filesystem from site root recursively that needs to be handled by sitecake
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function findResources()
    {
        $this->resources = $this->__findResources();
        $this->cache->save('files', $this->resources);
    }

    /**
     * Returns a list of paths of CMS related files.
     * It looks for source files, images and uploaded files.
     * It ignores entries from .scignore filter the output list.
     *
     * @param  string $type Indicates what type of resources should be listed (which handler to use)
     *
     * @return array The output paths list
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function listResources($type = null)
    {
        if (empty($this->resources)) {
            $this->findResources();
        }

        $paths = array_keys($this->resources);

        if ($type === null) {
            return $paths;
        }

        return $this->filterResourcePaths($type, $paths);
    }

    /**
     * Returns list of draft resources found on filesystem
     *
     * @param null|string $type
     *
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function listDraftResources($type = null)
    {
        $resources = $this->__findResources($this->draftPath);

        if ($type !== null) {
            return $this->filterResourcePaths($type, array_keys($resources));
        }

        return array_keys($resources);
    }
    //</editor-fold>

    //<editor-fold desc="Cache related methods">

    /**
     * Sets passed data under passed key for passed resource path
     *
     * @param string $path
     * @param string $key
     * @param mixed  $metadata
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function storeResourceMetadata($path, $key, $metadata)
    {
        if (empty($this->resources)) {
            $this->findResources();
        }
        if (!isset($this->resources[$path][2])) {
            $this->resources[$path][2] = [];
        }
        $this->resources[$path][2][$key] = $metadata;
        $this->cache->save('files', $this->resources);
    }

    /**
     * Returns metadata stored under passed key por passed resource path
     *
     * @param string $path
     * @param string $key
     *
     * @return null|array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getResourceMetadata($path, $key)
    {
        if (empty($this->resources)) {
            $this->findResources();
        }
        if (isset($this->resources[$path][2][$key])) {
            return $this->resources[$path][2][$key];
        }

        return null;
    }

    /**
     * Returns array containing timestamps of source and draft modification times for passed path.
     * Source timestamp is first and draft timestamp is second element of returning array
     *
     * @param string $path
     *
     * @return array
     */
    public function getResourceTimestamps($path)
    {
        return [
            $this->resources[$path][0],
            isset($this->resources[$path][1]) ? $this->resources[$path][1] : false
        ];
    }

    /**
     * Saves last modification time for passed path.
     * If second parameter not passed timestamp is calculated.
     *
     * @param string $path
     * @param int    $timestamp
     *
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function saveLastModified($path, $timestamp = null)
    {
        if (empty($this->resources)) {
            $this->findResources();
        }

        $index = 0;

        $filePath = $path;

        if ($this->isDraftResourcePath($path)) {
            $filePath = $this->stripDraftPath($path);
            $index = 1;
        }

        if ($timestamp === null) {
            $meta = $this->fs->getMetadata($path);
            $timestamp = $meta['timestamp'];
        }


        if (!isset($this->resources[$filePath])) {
            $this->resources[$filePath] = [];
        }
        $this->resources[$filePath][$index] = $timestamp;
        $this->cache->save('files', $this->resources);
    }

    /**
     * Marks specific source file path as dirty
     *
     * @param string $draftPath
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function markPathDirty($draftPath)
    {
        $unpublished = $this->cache->get('unpublished', []);

        if (array_search($draftPath, $unpublished) === false) {
            $unpublished[] = $draftPath;
            $this->cache->save('unpublished', $unpublished);
        }
    }

    /**
     * Returns list of paths that needs to be published
     *
     * @return array|bool|mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getUnpublishedPaths()
    {
        return $this->cache->get('unpublished', []);
    }

    /**
     * Removes references of passed resource from metadata.
     * If second parameter is false, removes only reference for page metadata. All references are removed otherwise
     *
     * @param string $path
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function removeResourceFromMetadata($path)
    {
        if (isset($this->resources[$path])) {
            unset($this->resources[$path]);
            $this->cache->save('files', $this->resources);
        }
    }

    //</editor-fold>

    //<editor-fold desc="Resource URL/Paths related methods">

    /**
     * Returns draft path for resource if passed.
     * Without arguments, method returns the path of the draft directory.
     *
     * @param string $resource
     * @param bool   $full Indicates whether to return relative or absolute path
     *
     * @return string the draft dir path
     */
    public function getDraftPath($resource = null, $full = false)
    {
        $path = $this->draftPath . ($resource === null ? '' : '/' . $resource);

        return $full ? $this->fs->fullPath($path) : $path;
    }

    /**
     * Strips draft portion of a passed path
     *
     * @param $path
     *
     * @return bool|string
     */
    public function stripDraftPath($path)
    {
        if (strpos($path, $this->draftBaseUrl()) === 0) {
            return substr($path, strlen($this->draftBaseUrl()));
        }

        return $path;
    }

    /**
     * Returns base path for draft resources
     *
     * @return string
     */
    public function draftBaseUrl()
    {
        return $this->getDraftPath() . '/';
    }

    /**
     * Returns draft URL for passed resource URL
     *
     * @param string $resourceUrl
     *
     * @return string
     */
    public function getDraftUrl($resourceUrl)
    {
        $base = $this->base();
        if (strpos('/' . $resourceUrl, $base) === 0) {
            $resourceUrl = $this->stripBase('/' . $resourceUrl);
        }
        $resourceUrl = $this->stripDraftPath($resourceUrl);

        return $base . $this->draftBaseUrl() . $resourceUrl;
    }

    /**
     * Creates a resource URL out of the given components.
     *
     * @param  string $path  resource path prefix (directory) or full resource path (dir, name, ext)
     * @param  string $name  resource name
     * @param  string $id    13-digit resource ID (uniqid)
     * @param  string $subId resource additional id (classifier, subid)
     * @param  string $ext   extension
     *
     * @return string        calculated resource path
     */
    public static function buildResourceUrl($path, $name = null, $id = null, $subId = null, $ext = null)
    {
        $id = ($id == null) ? uniqid() : $id;
        $subId = ($subId == null) ? '' : $subId;
        if ($name == null || $ext == null) {
            $pathInfo = pathinfo($path);
            $name = ($name == null) ? $pathInfo['filename'] : $name;
            $ext = ($ext == null) ? $pathInfo['extension'] : $ext;
            $path = ($pathInfo['dirname'] === '.') ? '' : $pathInfo['dirname'];
        }
        $path = $path . (($path === '' || substr($path, -1) === '/') ? '' : '/');
        $name = str_replace(' ', '_', $name);
        $ext = strtolower($ext);

        return $path . $name . '-sc' . $id . $subId . '.' . $ext;
    }

    /**
     * Extracts information from a resource URL.
     * It returns path, name, id, subid and extension.
     *
     * @param $urls
     *
     * @return array URL components (path, name, id, subid, ext) or a list of URL components
     * @internal param array|string $url a URL to be deconstructed or a list of URLs
     *
     */
    public function resourceUrlInfo($urls)
    {
        if (is_array($urls)) {
            $res = [];
            foreach ($urls as $url) {
                array_push($res, self::__resourceUrlInfo($url));
            }

            return $res;
        } else {
            return self::__resourceUrlInfo($urls);
        }
    }

    /**
     * Returns path, name, id, subid and extension from passed resource URL.
     *
     * @param string $url
     *
     * @return array
     */
    private function __resourceUrlInfo($url)
    {
        preg_match('/((.*)\/)?([^\/]+)-sc([0-9a-fA-F]{13})([^\.]*)\.([^\.]+)$/', $url, $match);

        return [
            'path' => $match[2],
            'name' => $match[3],
            'id' => $match[4],
            'subid' => $match[5],
            'ext' => $match[6]
        ];
    }

    /**
     * Maps passed URL to file path based on from where is that URL referenced
     *
     * @param string $url
     * @param string $refererPath
     *
     * @return string
     */
    public function urlToPath($url, $refererPath = '')
    {
        $path = $url;

        // Strip anchor from URL if present
        $path = explode(
            '#',
            // Strip '.' in front of url if it starts with './'
            ltrim($path, '.')
        );
        $path = array_shift($path);

        // Strip query string from URL if present
        $path = explode('?', $path);
        $path = array_shift($path);

        // Strip base dir url (just '/' if no base dir) if present
        $path = ltrim($this->stripBase($path), '/');

        try {
            $referenceDir = rtrim(
                implode('/', array_slice(explode('/', $refererPath), 0, -1)),
                '/'
            );
            $path = Util::normalizePath((strpos($path, '../') !== false
                    ? $referenceDir . '/' : '') . $path);

            // TODO: For empty path SourceFile handler will not be triggered. Need to fix this.
            $handler = $this->getSupportingHandler($path);
            if ($handler !== null) {
                return $handler->normalizePath($path);
            }
        } catch (\LogicException $e) {
            return $path;
        }

        return $path;
    }

    /**
     * Maps passed resource path to URL based on from where that resource should be referenced
     * and pages.use_document_relative_paths config value
     *
     * @param string $path
     * @param string $refererPath
     *
     * @return string
     */
    public function pathToUrl($path, $refererPath = '')
    {
        $url = $path;
        if (($handler = $this->getSupportingHandler($path)) !== null) {
            $url = $handler->pathToUrl($path);
        }
        if (!empty(Sitecake::getConfig('pages.use_document_relative_paths'))) {
            return $this->base() . $url;
        }

        $pathParts = explode('/', $url);
        $basename = array_pop($pathParts);

        $refererPathParts = array_slice(explode('/', $refererPath), 0, -1);

        $urlPrefix = '';
        $no = 0;
        foreach ($pathParts as $no => $part) {
            if (isset($refererPathParts[$no])) {
                if ($part == $refererPathParts[$no]) {
                    continue;
                }

                $urlPrefix = str_repeat('../', count(array_slice($refererPathParts, $no))) .
                    implode('/', array_slice($pathParts, $no));
                break;
            } else {
                $urlPrefix = implode('/', array_slice($pathParts, $no));
            }
        }

        if (empty($urlPrefix)) {
            if (empty($pathParts)) {
                $urlPrefix = str_repeat('../', count($refererPathParts));
            } elseif (isset($refererPathParts[$no + 1])) {
                $urlPrefix = str_repeat('../', count(array_slice($refererPathParts, $no + 1)));
            }
        }

        if (!empty($urlPrefix)) {
            return rtrim($urlPrefix, '/') . '/' . $basename;
        } else {
            return empty($basename) ? './' : $basename;
        }
    }

    /**
     * Returns base dir for website
     * e.g. if site is under http://www.sitecake.com/demo method will return /demo/
     *
     * @return string
     */
    public function base()
    {
        if (isset($this->base)) {
            return $this->base;
        }
        $self = (string)$_SERVER['PHP_SELF'];

        $serviceURLPosition = strlen($self) - strlen($this->paths['SERVICE_URL']) - 1;
        if (strpos($self, '/' . $this->paths['SERVICE_URL']) === $serviceURLPosition) {
            $base = str_replace('/' . $this->paths['SERVICE_URL'], '', $self);
        } else {
            $base = dirname($self);
        }

        $base = preg_replace('#/+#', '/', $base);

        if ($base === DIRECTORY_SEPARATOR || $base === '.') {
            $base = '';
        }
        $base = implode('/', array_map('rawurlencode', explode('/', $base)));

        return $this->base = $base . '/';
    }

    /**
     * Returns base path based on 'pages.use_document_relative_paths' config var
     *
     * @return string
     */
    public function getBase()
    {
        if (!empty(Sitecake::getConfig('pages.use_document_relative_paths'))) {
            return $this->base();
        }

        return '';
    }

    /**
     * Returns passed page url modified by stripping base dir if it exists
     *
     * @param string $path
     *
     * @return string Passed url stripped by base dir if found
     */
    protected function stripBase($path)
    {
        $check = $path;
        $base = $this->base();
        if (strpos($check, $base) === 0) {
            return (string)substr($check, strlen($base));
        }

        return $path;
    }
    //</editor-fold>
}
