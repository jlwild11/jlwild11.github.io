<?php

namespace Sitecake\Services\Image;

use Sitecake\Exception\Http\BadRequestException;
use Sitecake\Services\Service;
use Sitecake\Site;
use Sitecake\Util\Image;
use Sitecake\Util\Text;
use Sitecake\Util\Utils;
use WideImage\WideImage;

class ImageServiceNew extends Service
{
    protected static $_imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];

    /**
     * @var \Silex\Application
     */
    protected $_ctx;

    public function __construct(Site $site, $alias, array $config = [])
    {
        parent::__construct($site, $alias, $config);
    }

    /**
     * Upload service
     *
     * @param $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function upload($request)
    {
        // obtain the uploaded file, load image and read its details (filename, extension)
        if (!$request->headers->has('x-filename')) {
            throw new BadRequestException('Filename is missing (header X-FILENAME)');
        }
        $filename = base64_decode($request->headers->get('x-filename'));
        $pathinfo = pathinfo($filename);

        if (!in_array(strtolower($pathinfo['extension']), self::$_imageExtensions)) {
            return $this->json($request, [
                'status' => 1,
                'errMessage' => "$filename is not an image file"
            ], 200);
        }

        $filename = Text::sanitizeFilename($pathinfo['filename']);
        $ext = $pathinfo['extension'];
        $img = new Image('php://input');
        $img->filename($filename);
        $img->extension($ext);

        // generate image set
        $res = $this->_save($img);

        $res = [
            'status' => 0,
            'srcset' => $res['srcset'],
            'ratio' => $res['ratio']
        ];

        return $this->json($request, $res, 200);
    }

    /**
     * @param Image $img
     *
     * @return array Array of generated images information and ratio
     *               Images information contains its width, height and url (relative path)
     */
    public function _save($img)
    {
        $width = $img->width();
        $height = $img->height();
        $ratio = $width / $height;

        $widths = $this->_ctx['image.srcset_widths'];
        rsort($widths);

        if (!$img->isAnimated()) {
            $maxWidth = $widths[0];
            if ($width > $maxWidth) {
                $width = $maxWidth;
            }
        }

        $id = uniqid();
        $path = Utils::resourceUrl($this->__imgDir(), $img->filename(false), $id, '-' . $width, $img->format());
        $this->_ctx['fs']->write($path, $img);
        $this->_ctx['site']->saveLastModified($path);

        $srcset = [['width' => $width, 'height' => $height, 'url' => $path]];

        if (!$img->isAnimated()) {
            $srcset = array_merge($this->_generateImageSet($img, $id));
        }

        return ['srcset' => $srcset, 'ratio' => $ratio];
    }

    /**
     * Returns image draft directory path
     *
     * @return string
     */
    private function __imgDir()
    {
        return $this->_ctx['site']->draftPath() . '/images';
    }

    /**
     * Generates different image sizes for passed image based on defined 'image.srcset_widths' and
     * 'image.srcset_width_max_diff' values from configuration
     *
     * @param Image $img
     * @param string $id
     *
     * @return array Array of generated images information
     *               Images information contains its width, height and url (relative path)
     */
    protected function _generateImageSet($img, $id)
    {
        $width = $img->width();

        $widths = $this->_ctx['image.srcset_widths'];
        $maxDiff = $this->_ctx['image.srcset_width_max_diff'];
        rsort($widths);

        $maxWidth = $widths[0];
        if ($width > $maxWidth) {
            $width = $maxWidth;
        }

        $srcset = [];
        foreach ($this->__neededWidths($width, $widths, $maxDiff) as $targetWidth) {
            $tpath = Utils::resourceUrl($this->__imgDir(), $img->filename(false), $id, '-' . $width, $img->format());
            $timg = $img->resize($targetWidth);
            $targetHeight = $timg->height();
            $this->_ctx['fs']->write($tpath, $timg);
            $this->_ctx['site']->saveLastModified($tpath);
            unset($timg);
            array_push($srcset, ['width' => $targetWidth, 'height' => $targetHeight, 'url' => $tpath]);
        }

        return $srcset;
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
        $res = [];
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
     */
    public function uploadExternal($request)
    {
        if (!$request->request->has('src')) {
            throw new BadRequestException('Image URI is missing');
        }

        $uri = $request->request->get('src');
        $img = new Image($uri);

        // generate image set
        $res = $this->_save($img);

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
     */
    public function image($request)
    {
        if (!$request->request->has('image')) {
            throw new BadRequestException('Image URI is missing');
        }
        $uri = $request->request->get('image');

        if (!$request->request->has('data')) {
            throw new BadRequestException('Image transformation data is missing');
        }
        $data = $request->request->get('data');

        if (!$this->_ctx['fs']->has($uri)) {
            throw new BadRequestException(sprintf('Source image not found (%s)', $uri));
        }
        $img = WideImage::loadFromString($this->_ctx['fs']->read($uri));
        $img = new Image($uri);

        if (Utils::isScResourceUrl($uri)) {
            $info = Utils::resourceUrlInfo($uri);
        } else {
            $pathinfo = pathinfo($uri);
            $info = ['name' => $pathinfo['filename'], 'ext' => $pathinfo['extension']];
        }

        $datas = explode(':', $data);
        $left = $datas[0];
        $top = $datas[1];
        $width = $datas[2];
        $height = $datas[3];
        $filename = $info['name'];
        $ext = $info['ext'];

        $img = $this->_transformImage($img, $top, $left, $width, $height);

        // generate image set
        $res = $this->_generateImageSet($img, $filename, $ext);

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
     * @param Image $img
     * @param float $top
     * @param float $left
     * @param float $width
     * @param float $height
     *
     * @return Image
     */
    protected function _transformImage($img, $top, $left, $width, $height)
    {
        return $img->crop($left . '%', $top . '%', $width . '%', $height . '%');
    }
}
