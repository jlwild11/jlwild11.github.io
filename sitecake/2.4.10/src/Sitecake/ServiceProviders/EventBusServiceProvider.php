<?php

namespace Sitecake\ServiceProviders;

use Cake\Event\EventManager;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class EventBusServiceProvider implements ServiceProviderInterface
{
    public function register(Container $pimple)
    {
        $pimple['event_bus'] = function () {
            return EventManager::instance();
        };
    }
}
