<?php

namespace Sitecake\ServiceProviders\File\Exception;

class InvalidPhpConfigException extends InvalidConfigSourceException
{
    protected $messageTemplate = 'Config file "%s" doesn\'t define $config variable or return array';
}
