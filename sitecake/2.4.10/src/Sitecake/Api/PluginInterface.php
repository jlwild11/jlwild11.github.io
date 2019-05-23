<?php

namespace Sitecake\Api;

interface PluginInterface
{
    /**
     * PluginInterface constructor.
     *
     * @param Sitecake $sitecake Sitecake api instance
     * @param array    $config Plugin configuration
     */
    public function __construct(Sitecake $sitecake, array $config = []);
}
