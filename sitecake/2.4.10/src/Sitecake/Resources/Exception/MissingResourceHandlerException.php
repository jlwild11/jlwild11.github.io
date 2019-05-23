<?php

namespace Sitecake\Resources\Exception;

use Sitecake\Exception\Exception;

class MissingResourceHandlerException extends Exception
{
    protected $messageTemplate = 'Resource handler %s not found';
}
