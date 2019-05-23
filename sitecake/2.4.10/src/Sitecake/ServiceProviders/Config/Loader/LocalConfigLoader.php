<?php

namespace Sitecake\ServiceProviders\Config\Loader;

use Sitecake\Exception\ConfigSourceNotFoundException;
use Sitecake\ServiceProviders\Config\AbstractConfigLoader;
use Sitecake\ServiceProviders\File\Exception\InvalidConfigSourceException;

abstract class LocalConfigLoader extends AbstractConfigLoader
{
    /**
     * {@inheritdoc}
     */
    public function load($source)
    {
        if (!stream_is_local($source)) {
            return false;
        }

        if (is_string($source) && !file_exists($source)) {
            throw new ConfigSourceNotFoundException([
                'source' => $source
            ]);
        }

        try {
            return $this->loadConfiguration($source);
        } catch (InvalidConfigSourceException $e) {
            return false;
        }
    }

    /**
     * Loads passed resource and returns loaded configuration
     *
     * @param string|resource $source
     *
     * @return array
     *
     * @throws InvalidConfigSourceException If stream content has an invalid format.
     */
    abstract protected function loadConfiguration($source);
}
