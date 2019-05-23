<?php

namespace Sitecake\Services;

use Sitecake\Cache;
use Sitecake\Exception\Http\BadRequestException;
use Sitecake\Resources\ResourceManager;
use Sitecake\Site;
use Sitecake\Util\Text;
use Symfony\Component\HttpFoundation\Request;

class UploadService extends Service
{
    /**
     * Forbidden extensions for uploaded files.
     *
     * @var array
     */
    protected $forbiddenExtensions;

    /**
     * Name of directory where uploaded files are being stored
     *
     * @var string
     */
    protected $uploadDirName;

    /**
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @var Cache
     */
    protected $metadata;

    /**
     * UploadService constructor.
     *
     * {@inheritdoc}
     */
    public function __construct(Site $site, $alias, array $config = [])
    {
        parent::__construct($site, $alias, $config);
        $this->resourceManager = $this->getConfig('resourceManager');
        $this->metadata = $this->getConfig('metadata');
        $this->forbiddenExtensions = $this->getConfig(
            'forbiddenExtensions',
            ['php', 'php5', 'php4', 'php3', 'phtml', 'phpt']
        );
        $this->uploadDirName = $this->getConfig('uploadDirName');
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function upload(Request $request)
    {
        if (!$request->headers->has('x-filename')) {
            throw new BadRequestException('Filename is missing (header X-FILENAME)');
        }
        $filename = base64_decode($request->headers->get('x-filename'));
        $pathInfo = pathinfo($filename);
        $destinationPath = $this->resourceManager->buildResourceUrl(
            $this->uploadDirName,
            Text::sanitizeFilename($pathInfo['filename']),
            null,
            null,
            $pathInfo['extension']
        );

        if (!$this->isSafeExtension($pathInfo['extension'])) {
            return $this->json($request, [
                'status' => 1,
                'errMessage' => 'Forbidden file extension ' . $pathInfo['extension']
            ], 200);
        }

        if (!$this->resourceManager->createDraft($destinationPath, fopen("php://input", 'r'))) {
            return $this->json($request, [
                'status' => 1,
                'errMessage' => 'Unable to upload file ' . $pathInfo['filename'] . '.' . $pathInfo['extension']
            ], 200);
        } else {
            $referer = parse_url($request->headers->get('referer'), PHP_URL_QUERY);

            $path = '';
            if (!empty($referer) && strpos($referer, 'scpage=') !== false) {
                parse_str($referer, $query);
                $path = $query['scpage'];
            }

            return $this->json($request, [
                'status' => 0,
                'url' => $this->resourceManager->pathToUrl(
                    $destinationPath,
                    $path
                )
            ], 200);
        }
    }

    protected function isSafeExtension($ext)
    {
        return !in_array(strtolower($ext), $this->forbiddenExtensions);
    }
}
