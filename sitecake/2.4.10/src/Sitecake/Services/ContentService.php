<?php

namespace Sitecake\Services;

use Sitecake\Exception\Http\BadRequestException;
use Sitecake\PageManager\PageManager;
use Sitecake\Site;

class ContentService extends Service
{
    /**
     * @var PageManager
     */
    protected $pageManager;

    public function __construct(Site $site, $alias, array $config = [])
    {
        parent::__construct($site, $alias, $config);
        $this->pageManager = $this->getConfig('pageManager');
    }

    public function save($request)
    {
        $pageID = $request->request->get('scpageid');
        if (is_null($pageID)) {
            throw new BadRequestException('Page ID is missing');
        }
        $request->request->remove('scpageid');

        $contents = $request->request->all();
        foreach ($contents as $containerName => &$content) {
            // remove slashes
            if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
                $content = stripcslashes($content);
            }
            // Decode content
            $content = base64_decode($content);
        }

        try {
            $this->pageManager->saveDraft($pageID, $contents);

            $this->site->markDraftDirty();

            return $this->json($request, ['status' => 0]);
        } catch (\Exception $e) {
            return $this->json($request, [
                'status' => 1,
                'errMessage' => $e->getMessage()
            ]);
        }
    }

    public function publish($request)
    {
        try {
            $this->site->publishDraft();

            return $this->json($request, ['status' => 0]);
        } catch (\Exception $e) {
            return $this->json($request, [
                'status' => 1,
                'errMessage' => $e->getMessage()
            ]);
        }
    }
}
