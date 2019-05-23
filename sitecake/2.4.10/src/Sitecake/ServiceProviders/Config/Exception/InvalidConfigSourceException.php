<?php

namespace Sitecake\ServiceProviders\File\Exception;

use Sitecake\Exception\Exception;

class InvalidConfigSourceException extends Exception
{
    protected $messageTemplate = 'Passed configuration source "%s" couldn\'t be loaded.';
}
