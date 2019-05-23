<?php

namespace Sitecake\ServiceProviders\Config\Loader;

use Sitecake\ServiceProviders\File\Exception\InvalidPhpConfigException;

class PhpConfigLoader extends LocalConfigLoader
{
    /**
     * {@inheritdoc}
     */
    protected function loadConfiguration($source)
    {
        if (is_string($source)) {
            $return = include $source;
            if (is_array($return)) {
                return $return;
            }

            if (!isset($config)) {
                throw new InvalidPhpConfigException($source);
            }

            return $config;
        }

        return false;
    }
}
