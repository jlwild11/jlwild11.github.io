<?php

namespace Sitecake\PageManager;

use Sitecake\PageManager\Exception\TemplateNotFoundException;
use Sitecake\Resources\SourceFile;
use Sitecake\Sitecake;

class TemplateManager extends SourceFileHandler implements TemplateManagerInterface
{
    /**
     * @param $identifier
     *
     * @return SourceFile
     */
    public function getTemplate($identifier)
    {
        $metadata = Sitecake::cache()->get('pages', []);

        $sourcePath = '';
        foreach ($metadata as $path => $details) {
            if ($details['id'] == $identifier) {
                $sourcePath = $path;
                break;
            }
        }

        // TODO: consider creating page out of public resource and not draft
        if (empty($sourcePath) || !$this->resourceManager->draftExists($sourcePath)) {
            throw new TemplateNotFoundException([
                'type' => 'Source Page',
                'files' => $sourcePath
            ], 401);
        }

        $draftPath = $this->resourceManager->getDraftPath($sourcePath);

        return new SourceFile($this->resourceManager->read($draftPath), $sourcePath);
    }
}
