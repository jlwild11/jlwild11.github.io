<?php

namespace Sitecake\ServiceProviders\Config;

use Sitecake\Util\InstanceConfigTrait;

abstract class AbstractConfigLoader implements ConfigLoaderInterface
{
    use InstanceConfigTrait;

    protected $defaultConfig = [];

    protected $registry;

    public function __construct(ConfigLoaderRegistry $registry, array $config = [])
    {
        $this->registry = $registry;
        $this->setConfig($config);
    }
}
