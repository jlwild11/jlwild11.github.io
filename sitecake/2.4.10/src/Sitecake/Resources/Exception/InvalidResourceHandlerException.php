<?php

namespace Sitecake\Resources\Exception;

use Sitecake\Exception\Exception;

class InvalidResourceHandlerException extends Exception
{
    protected $messageTemplate = 'Resource handler %s is not registered';
}
