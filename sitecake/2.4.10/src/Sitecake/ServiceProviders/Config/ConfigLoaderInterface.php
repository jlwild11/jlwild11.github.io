<?php

namespace Sitecake\ServiceProviders\Config;

use Sitecake\Exception\ConfigSourceNotFoundException;
use Sitecake\ServiceProviders\File\Exception\InvalidConfigSourceException;

interface ConfigLoaderInterface
{
    /**
     * Loads a resource with configuration values.
     *
     * @param mixed $source Configuration source
     *
     * @return array|bool Configuration array or false on failure
     * @throws ConfigSourceNotFoundException
     */
    public function load($source);
}
