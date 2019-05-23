<?php

namespace Sitecake;

use Cake\Event\Event;
use Sitecake\Filesystem\Filesystem;
use Sitecake\Resources\ResourceManager;
use Sitecake\Util\Beautifier;

class Site
{
    const DRAFT_MARKER_FILENAME = 'draft.mkr';
    const DRAFT_DIRTY_FILENAME = 'draft.drt';

    /**
     * Indicates whether site is initialized or not
     *
     * @var bool
     */
    protected $initialized = false;
    /**
     * SITE_ROOT relative path to 'draft' dir
     *
     * @var string
     */
    protected $draftPath;

    /**
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * Backup manager instance
     *
     * @var BackupManager
     */
    protected $backupManager;

    /**
     * Filesystem instance
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Sitecake paths
     *
     * @var array
     */
    protected $paths;

    /**
     * Stores base dir
     *
     * @var string
     */
    protected $base;

    /**
     * Stores default index page name(s)
     *
     * @var array
     */
    protected $indexes = [];

    /**
     * Default pages specified in app configuration
     *
     * @var array
     */
    protected $defaultPages = [];

    public function __construct(
        Filesystem $fs,
        ResourceManager $resourceManager,
        BackupManager $backupManager
    ) {
        $this->fs = $fs;
        $this->resourceManager = $resourceManager;
        $this->backupManager = $backupManager;
        $this->paths = Sitecake::getPaths();

        // Read default pages from configuration
        $this->defaultPages = (array)Sitecake::getConfig('site.default_pages');

        // Initialize beautifier
        Beautifier::config([
            'indentation_character' => Sitecake::getConfig('content.indent', '    ')
        ]);

        // TODO: Remove this in 2.4.9 release. Used just to persist existing cache
        if ($existingMetadata = $this->loadMetadata()) {
            Sitecake::cache()->initCache($existingMetadata);
            $this->fs->put($this->draftMarkerPath(), '');
        }
    }

    /**
     * Retrieves site metadata from file.
     *
     * @return array|bool If metadata written to file can't be un-serialized
     * @deprecated Method is used only as a bridge between old and new cache handling
     *
     * TODO: Remove this in 2.4.9 release. Used just to persist existing cache
     */
    protected function loadMetadata()
    {
        if ($this->draftExists()) {
            try {
                if (($content = $this->fs->read($this->draftMarkerPath())) != '') {
                    if ($metadata = @unserialize($content)) {
                        return $metadata;
                    } elseif ($metadata = @json_decode($content, true)) {
                        return $metadata;
                    }
                }
            } catch (\Exception $e) {
            }
        }

        return false;
    }

    /**
     * Starts the site draft out of the public content.
     * It copies public pages and resources into the draft folder.
     *
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function initialize()
    {
        if (!$this->initialized) {
            $draftExists = $this->draftExists();

            // Fire onInitialize event
            Sitecake::eventBus()->dispatch('Site.onInitialize');

            if (!$draftExists) {
                $this->createDraft();
            } else {
                $this->updateFromSource();
            }

            $this->initialized = true;
        }
    }

    /**
     * Starts site draft. Copies all pages and resources to draft directory.
     * Also prepares container names, prefixes all urls in draft pages
     * and collects all navigation sections that appears inside pages.
     *
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function createDraft()
    {
        // Copy and prepare all resources and pages (normalize containers and prefix resource urls) and load navigation
        $paths = $this->resourceManager->listResources();

        // Fire on draft create event
        $event = Sitecake::eventBus()->dispatch(new Event('Site.onDraftCreate', $this, [
            'paths' => $paths
        ]));

        $result = $event->getResult();
        if (is_array($result)) {
            $paths = array_merge($paths, $result);
        }

        foreach ($paths as $path) {
            $this->resourceManager->createDraft($path);
        }

        // Create draft marker
        $this->fs->put($this->draftMarkerPath(), '');

        // Set lastPublished metadata value to current timestamp
        $this->saveLastPublished();
    }

    /**
     * Updates draft resources from original and updates metadata
     *
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function updateFromSource()
    {
        // Check if draft is published
        $isPublished = $this->isPublished();

        // Get all resources to check if some are outdated and needs to be overwritten
        $paths = $this->resourceManager->listResources();

        // Get all draft page files to be able to compare and delete files that don't exist any more
        $draftPaths = $this->resourceManager->listDraftResources();

        // Fire on draft update event
        $event = Sitecake::eventBus()->dispatch(new Event('Site.onDraftUpdate', $this, [
            'isPublished' => $isPublished,
            'paths' => $paths
        ]));

        $result = $event->getResult();
        if (is_array($result)) {
            $paths = array_merge($paths, $result);
        }

        foreach ($paths as $path) {
            $draftPath = $this->resourceManager->draftBaseUrl() . $path;

            // Filter out draft path from all draft paths
            if (($index = array_search($draftPath, $draftPaths)) !== false) {
                unset($draftPaths[$index]);
            }

            if (!$this->fs->has($draftPath)) {
                // This is a new resource so draft should be created
                $this->resourceManager->createDraft($path);
            } else {
                list($sourceTimestamp, $draftTimestamp) = $this->resourceManager->getResourceTimestamps($path);
                // Check if draft file last modification time exists in metadata
                if ($draftTimestamp) {
                    // Check last modification time for resource and overwrite draft file if it is needed and possible
                    if ($sourceTimestamp > $draftTimestamp) {
                        // If there are no unpublished changes or manual changes have priority re-create draft
                        if ($isPublished || Sitecake::getConfig('pages.prioritize_manual_changes')) {
                            $this->fs->delete($draftPath);
                            $this->resourceManager->createDraft($path);
                        }
                    }
                } else {
                    $draftMetadata = $this->fs->getMetadata($draftPath);
                    if ($isPublished || ($this->getLastPublished() > $draftMetadata['timestamp'])) {
                        $this->fs->delete($draftPath);
                        $this->resourceManager->createDraft($path);
                    } else {
                        // Remember last modification times
                        $this->resourceManager->saveLastModified($draftPath, $draftMetadata['timestamp']);
                    }
                }
            }
        }

        if (!empty($draftPaths) && ($isPublished || Sitecake::getConfig('pages.prioritize_manual_changes'))) {
            foreach ($draftPaths as $draftPath) {
                /**
                 * TODO: For now if page is deleted manually it's draft should also be deleted.
                 * This should be changed when unpublished changes are introduced
                 */
                $this->resourceManager->delete($draftPath);
            }
        }
    }

    /**
     * Publishes changed draft files
     *
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function publishDraft()
    {
        if ($this->draftExists()) {
            // Backup site
            $this->backupManager->backup();

            // Get all modified/added paths
            $unpublishedResources = $this->resourceManager->getUnpublishedPaths();

            // Publish draft
            // Fire beforePublish event. Could be used for creating backup, cleaning tmp files...
            Sitecake::eventBus()->dispatch(new Event('Site.beforePublish'));

            foreach ($unpublishedResources as $no => $path) {
                $publicPath = $this->resourceManager->stripDraftPath($path);
                // Overwrite live file with draft only if draft actually exists
                if ($this->resourceManager->exists($path)) {
                    $this->resourceManager->publish($path);
                } else {
                    // If draft file is missing we need to delete original
                    if ($this->resourceManager->resourceExists($publicPath)) {
                        $this->resourceManager->delete($publicPath);
                    }
                }
            }

            // Save last publishing time
            $this->saveLastPublished();
            // Mark draft clean
            $this->markDraftClean();
        }
    }

    /**
     * Saves lastPublished metadata value. Called after publish event finishes
     */
    protected function saveLastPublished()
    {
        Sitecake::cache()->save('lastPublished', time());
    }

    /**
     * Returns lastPublished metadata value.
     */
    public function getLastPublished()
    {
        return Sitecake::cache()->get('lastPublished');
    }

    //<editor-fold desc="Draft markers methods">

    /**
     * Checks if draft is created
     *
     * @return bool
     */
    protected function draftExists()
    {
        return $this->fs->has($this->draftMarkerPath());
    }

    /**
     * Returns path for draft.mkr file
     *
     * @return string
     */
    protected function draftMarkerPath()
    {
        return $this->resourceManager->getDraftPath(self::DRAFT_MARKER_FILENAME);
    }

    /**
     * Returns path for 'draft.drt' marker file
     *
     * @return string
     */
    protected function draftDirtyMarkerPath()
    {
        return $this->resourceManager->getDraftPath(self::DRAFT_DIRTY_FILENAME);
    }

    /**
     * Returns whether all changes are published
     *
     * @return bool
     */
    public function isPublished()
    {
        return !$this->fs->has($this->draftDirtyMarkerPath());
    }

    /**
     * Marks that all changes are published
     *
     * @throws \League\Flysystem\FileNotFoundException
     */
    protected function markDraftClean()
    {
        if ($this->fs->has($this->draftDirtyMarkerPath())) {
            $this->fs->delete($this->draftDirtyMarkerPath());
        }

        Sitecake::cache()->save('unpublished', []);
    }

    /**
     * Marks that there are unsaved changes by create draft dirty file marker
     *
     * @throws \League\Flysystem\FileExistsException
     */
    public function markDraftDirty()
    {
        if (!$this->fs->has($this->draftDirtyMarkerPath())) {
            $this->fs->write($this->draftDirtyMarkerPath(), '');
        }
    }

    //</editor-fold>
}
