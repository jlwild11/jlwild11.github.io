<?php

namespace Sitecake\Util;

use Sitecake\Exception\InvalidArgumentException;
use Sitecake\Exception\Http\BadRequestException;
use WideImage\WideImage;

class Image
{
    /**
     * @var \WideImage\Image|\WideImage\PaletteImage|\WideImage\TrueColorImage
     */
    protected $img;

    /**
     * @var string
     */
    protected $source;

    /**
     * @var string
     */
    protected $path = '';

    /**
     * @var bool
     */
    protected $isVirtual;

    /**
     * @var bool
     */
    protected $isAnimated;

    /**
     * @var bool
     */
    protected $tmpName = '';

    /**
     * @var string
     */
    protected $filename;

    /**
     * @var string
     */
    protected $extension;

    /**
     * @var string
     */
    protected $format;

    /**
     * Image constructor.
     *
     * @param string $resource Image resource : URL, existing file path, input stream (php://input) or image data
     * @param string $format
     */
    public function __construct($resource, $format = '')
    {
        if (!is_string($resource)) {
            throw new InvalidArgumentException('Passed string is not a valid image resource');
        }

        if (Utils::isURL($resource)) {
            $this->loadFromURL($resource);
        } elseif (@file_exists($resource)) {
            $this->loadFromFile($resource);
        } elseif ($resource == 'php://input') {
            if (empty($format)) {
                throw new InvalidArgumentException('File format is mandatory for input stream image resource');
            }

            $this->loadFromInputStream($format);
        } elseif (imagecreatefromstring($resource) !== false) {
            if (empty($format)) {
                throw new InvalidArgumentException('File format is mandatory for image data resource');
            }

            $this->loadFromString($resource, $format);
        } else {
            throw new InvalidArgumentException('Passed string is not a valid image resource');
        }

        $this->isAnimated = $this->isAniGif($format);
        $this->img = WideImage::loadFromString($this);
    }

    protected function loadFromURL($url)
    {
        $referer = substr($url, 0, strrpos($url, '/'));
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        try {
            $this->source = $output;
            $parsedURL = parse_url($url);
            $this->setImageInfo($parsedURL['path']);
            $this->isVirtual = true;
        } catch (\Exception $e) {
            throw new BadRequestException(sprintf('Unable to load image from %s (referer: %s)', $url, $referer));
        }
    }

    protected function setImageInfo($path, $format = '')
    {
        $pathInfo = pathinfo($path);
        $this->filename = $pathInfo['filename'];
        $this->format = $this->extension = $pathInfo['extension'];
        if (!empty($format)) {
            $this->format = $format;
        }
        $this->path = realpath($pathInfo['dirname']);
    }

    protected function loadFromFile($path)
    {
        $this->setImageInfo($path);
        $this->isVirtual = false;
    }

    protected function loadFromInputStream($format)
    {
        $this->source = file_get_contents('php://input');
        $this->format = $format;
        $this->isVirtual = true;
    }

    protected function loadFromString($content, $format)
    {
        $this->source = $content;
        $this->format = $format;
        $this->isVirtual = true;
    }

    protected function isAniGif($format = '')
    {
        $filePath = $this->isVirtual ? $this->createTmpImage($format) : $this->filePath();
        // Create temporary file to read if image doesn't actually exists
        if (!($fh = @fopen($filePath, 'rb'))) {
            return false;
        }
        $count = 0;
        //an animated gif contains multiple "frames", with each frame having a
        //header made up of:
        // * a static 4-byte sequence (\x00\x21\xF9\x04)
        // * 4 variable bytes
        // * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21 ?)

        // We read through the file til we reach the end of the file, or we've found
        // at least 2 frame headers
        while (!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); //read 100kb at a time
            $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
        }

        fclose($fh);

        return $count > 1;
    }

    protected function createTmpImage($format = '')
    {
        $filePath = tempnam(sys_get_temp_dir(), 'Sag');

        $handle = @fopen($filePath, "w");
        fwrite($handle, (string)$this);
        fclose($handle);

        $this->setImageInfo($filePath, $format);

        return $filePath;
    }

    public function filePath()
    {
        return $this->path() . $this->filename();
    }

    public function path()
    {
        return empty($this->path) ? $this->path : $this->path . DIRECTORY_SEPARATOR;
    }

    public function filename($withExtension = true)
    {
        if (is_string($withExtension)) {
            $this->filename = $withExtension;
        } elseif (is_bool($withExtension)) {
            return $this->filename . ($withExtension ? '.' . $this->extension : '');
        }

        return $this->filename;
    }

    public function __destruct()
    {
        if ($this->isVirtual) {
            unlink($this->filePath());
        }
    }

    public function __toString()
    {
        try {
            return $this->content();
        } catch (\Exception $e) {
            return '';
        }
    }

    public function content()
    {
        return $this->isVirtual ? $this->source : file_get_contents($this->filePath());
    }

    public function width()
    {
        return $this->img->getWidth();
    }

    public function height()
    {
        return $this->img->getHeight();
    }

    /**
     * @param string $extension
     *
     * @return string|null
     */
    public function extension($extension = '')
    {
        if ($extension) {
            $this->extension = $extension;
        } else {
            return $this->extension;
        }
    }

    public function format()
    {
        return $this->format;
    }

    public function isAnimated()
    {
        return $this->isAnimated;
    }

    public function resize($targetWidth)
    {
        $this->img->resize($targetWidth);
        $this->source = $this->img->asString($this->format);

        return $this;
    }

    public function crop($top, $left, $width, $height)
    {
        $this->img->crop($left . '%', $top . '%', $width . '%', $height . '%');
        $this->source = $this->img->asString($this->format);

        return $this;
    }
}
