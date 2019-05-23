<?php

namespace Sitecake\ServiceProviders\File\Exception;

use Sitecake\Exception\Exception;

class ConfigLockedException extends Exception
{
    protected $messageTemplate = 'Configuration for "%s" is locked and could not be re-configured.';
}
