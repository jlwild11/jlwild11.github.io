<?php

namespace Sitecake\Services;

use Sitecake\Cache;
use Sitecake\Exception\Http\BadRequestException;
use Sitecake\Resources\ResourceManager;
use Sitecake\Site;
use Sitecake\Util\Text;
use Sitecake\Util\Utils;
use WideImage\Image;
use WideImage\WideImage;

class ImageService extends Service
{
    const DEFAULT_QUALITY = 75;
    /**
     * Allowed extensions for uploaded images.
     *
     * @var array
     */
    protected $imageExtensions;

    /**
     * Name of directory where images are being stored
     *
     * @var string
     */
    protected $imgDirName;

    /**
     * List of image widths in pixels that would be used for generating
     * images for srcset attribute.
     *
     * @see http://w3c.github.io/html/semantics-embedded-content.html#element-attrdef-img-srcset
     *
     * @var array
     */
    protected $srcSetWidths;

    /**
     * List of qualities to be used when generating images for srcset attribute.
     * Also can be set as int so same quality will be used for all generated images.
     * This quality can be used only when working with JPEG images
     *
     * @see http://php.net/manual/en/function.imagejpeg.php
     *
     * @var array|int
     */
    protected $srcSetQuality;

    /**
     * Max relative diff (in percents) between two image widths in pixels
     * so they could be considered similar
     *
     * @var int
     */
    protected $srcSetWidthMaxDiff;

    /**
     * Resource manager
     *
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * Metadata manager
     *
     * @var Cache
     */
    protected $metadata;

    /**
     * ImageService constructor.
     *
     * {@inheritdoc}
     */
    public function __construct(Site $site, $alias, array $config = [])
    {
        parent::__construct($site, $alias, $config);
        $this->resourceManager = $this->getConfig('resourceManager');
        $this->metadata = $this->getConfig('metadata');
        $this->imageExtensions = $this->getConfig('imageExtensions', ['jpg', 'jpeg', 'png', 'gif']);
        $this->imgDirName = $this->getConfig('imgDirName', 'images');
        $this->srcSetWidths = $this->getConfig('srcSetWidths', [1280, 960, 640, 320]);
        $this->srcSetQuality = $this->getConfig('srcSetQuality', self::DEFAULT_QUALITY);
        $this->srcSetWidthMaxDiff = $this->getConfig('srcSetWidthMaxDiff', 20);
    }

    /**
     * Upload service
     *
     * @param $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function upload($request)
    {

        // obtain the uploaded file, load image and read its details (filename, extension)
        if (!$request->headers->has('x-filename')) {
            throw new BadRequestException('Filename is missing (header X-FILENAME)');
        }
        $filename = base64_decode($request->headers->get('x-filename'));
        $pathInfo = pathinfo($filename);

        if (!in_array(strtolower($pathInfo['extension']), $this->imageExtensions)) {
            return $this->json($request, [
                'status' => 1,
                'errMessage' => "$filename is not supported image file"
            ], 200);
        }

        $filename = Text::sanitizeFilename($pathInfo['filename'], 'file-' . uniqid());
        $ext = $pathInfo['extension'];
        $img = WideImage::load("php://input");

        // generate image set
        $res = $this->generateImageSet($img, $filename, $ext);

        if (empty($res['srcset'])) {
            return $this->json($request, [
                'status' => 1,
                'errMessage' => 'Couldn\'t generate image set'
            ], 200);
        }

        $res = [
            'status' => 0,
            'srcset' => $res['srcset'],
            'ratio' => $res['ratio']
        ];

        return $this->json($request, $res, 200);
    }

    /**
     * Generates different image sizes for passed image based on defined 'image.srcset_widths' and
     * 'image.srcset_width_maxdiff' values from configuration
     *
     * @param \WideImage\Image $img
     * @param string $filename
     * @param string $ext
     *
     * @return array Array of generated images information and images ratio
     *               Images information contains its width, height and url (relative path)
     *
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function generateImageSet(Image $img, $filename, $ext)
    {
        $width = $img->getWidth();
        $ratio = $width / $img->getHeight();

        $widths = $this->srcSetWidths;
        $maxDiff = $this->srcSetWidthMaxDiff;
        rsort($widths);

        $maxWidth = $widths[0];
        if ($width > $maxWidth) {
            $width = $maxWidth;
        }

        $id = uniqid();

        $srcset = [];
        foreach ($this->__neededWidths($width, $widths, $maxDiff) as $targetWidth) {
            $targetPath = $this->resourceManager->buildResourceUrl(
                $this->imgDirName,
                $filename,
                $id,
                '-' . $targetWidth,
                $ext
            );
            $targetImage = $img->resize($targetWidth);
            $targetHeight = $targetImage->getHeight();
            $format = strtoupper(preg_replace('/[^a-z0-9_-]/i', '', $ext));
            if ($format === 'JPG' || $format === 'JPEG') {
                $quality = self::DEFAULT_QUALITY;
                if (is_array($this->srcSetQuality) && isset($this->srcSetQuality[(string)$targetWidth])) {
                    $quality = $this->srcSetQuality[(string)$targetWidth];
                } elseif (is_numeric($this->srcSetQuality)) {
                    $quality = (int)$this->srcSetQuality;
                }
                $binaryContent = $targetImage->asString($ext, $quality);
            } else {
                $binaryContent = $targetImage->asString($ext);
            }
            if ($draftPath = $this->resourceManager->createDraft($targetPath, $binaryContent)) {
                array_push($srcset, [
                    'width' => $targetWidth,
                    'height' => $targetHeight,
                    'url' => $this->resourceManager->base() . $draftPath
                ]);
            }
            unset($targetImage);
        }

        return ['srcset' => $srcset, 'ratio' => $ratio];
    }

    /**
     * Returns array of widths base on starting (maximum) width, list of possible widths and
     * maximum difference (in percents) between two image widths in pixels so they could be considered similar
     *
     * @param float $startWidth
     * @param array $widths
     * @param float $maxDiff
     *
     * @return array
     */
    private function __neededWidths($startWidth, $widths, $maxDiff)
    {
        $res = [$startWidth];
        rsort($widths);
        $first = true;
        foreach ($widths as $i => $width) {
            if (!$first || ($first && ($startWidth - $width) / $startWidth > $maxDiff / 100)) {
                array_push($res, $width);
                $first = false;
            }
        }

        return $res;
    }

    /**
     * External upload service
     *
     * @param $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function uploadExternal($request)
    {
        if (!$request->request->has('src')) {
            throw new BadRequestException('Image URI is missing');
        }

        $uri = $request->request->get('src');
        $referer = substr($uri, 0, strrpos($uri, '/'));
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        try {
            $img = WideImage::loadFromString($output);
        } catch (\Exception $e) {
            return $this->json($request, [
                'status' => 1,
                'errMessage' => sprintf('Unable to load image from %s (referer: %s)', $uri, $referer)
            ], 200);
        }
        unset($output);

        $urlInfo = parse_url($uri);
        $pathInfo = pathinfo($urlInfo['path']);
        $filename = $pathInfo['filename'];
        $ext = $pathInfo['extension'];

        // generate image set
        $res = $this->generateImageSet($img, $filename, $ext);

        if (empty($res['srcset'])) {
            return $this->json($request, [
                'status' => 1,
                'errMessage' => 'Couldn\'t generate image set'
            ], 200);
        }

        $res = [
            'status' => 0,
            'srcset' => $res['srcset'],
            'ratio' => $res['ratio']
        ];

        return $this->json($request, $res, 200);
    }

    /**
     * Transform image service
     *
     * @param $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function image($request)
    {
        if (!$request->request->has('image')) {
            throw new BadRequestException('Image URI is missing');
        }

        $referer = parse_url($request->headers->get('referer'), PHP_URL_QUERY);

        $refererPath = '';
        if (!empty($referer) && strpos($referer, 'scpage=') !== false) {
            parse_str($referer, $query);
            $refererPath = $query['scpage'];
        }
        $path = $this->resourceManager->urlToPath($request->request->get('image'), $refererPath);

        if (!$request->request->has('data')) {
            throw new BadRequestException('Image transformation data is missing');
        }
        $data = $request->request->get('data');

        // TODO: When all images becomes resources need also to check if path isResource
        if (!$this->resourceManager->exists($path)) {
            throw new BadRequestException(sprintf('Source image not found (%s)', $path));
        }
        $img = WideImage::loadFromString($this->resourceManager->read($path));

        if (Utils::isScResourceUrl($path)) {
            $info = $this->resourceManager->resourceUrlInfo($path);
        } else {
            $pathInfo = pathinfo($path);
            $info = ['name' => $pathInfo['filename'], 'ext' => $pathInfo['extension']];
        }

        $data = explode(':', $data);
        $left = $data[0];
        $top = $data[1];
        $width = $data[2];
        $height = $data[3];
        $filename = $info['name'];
        $ext = $info['ext'];

        $img = $this->transformImage($img, $top, $left, $width, $height);

        // generate image set
        $res = $this->generateImageSet($img, $filename, $ext);

        if (empty($res['srcset'])) {
            return $this->json($request, [
                'status' => 1,
                'errMessage' => 'Couldn\'t generate image set'
            ], 200);
        }

        $res = [
            'status' => 0,
            'srcset' => $res['srcset'],
            'ratio' => $res['ratio']
        ];

        return $this->json($request, $res, 200);
    }

    /**
     * Wrapper method for WideImage\Image::crop method
     *
     * @param \WideImage\Image $img
     * @param float $top
     * @param float $left
     * @param float $width
     * @param float $height
     *
     * @return \WideImage\Image
     */
    protected function transformImage(Image $img, $top, $left, $width, $height)
    {
        return $img->crop($left . '%', $top . '%', $width . '%', $height . '%');
    }
}
