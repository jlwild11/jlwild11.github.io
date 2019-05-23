<?php

namespace Sitecake\ServiceProviders\File\Exception;

use Sitecake\Exception\Exception;

class MissingConfigLoaderException extends Exception
{
    protected $messageTemplate = 'Configuration loader "%s" could not be found.';
}
