<?php

namespace Sitecake\Exception\Http;

use Sitecake\Exception\Exception;

class BadRequestException extends Exception
{
    protected $messageTemplate = '400 Bad Request : %s';

    public function __construct($message, $code = 400, $previous = null)
    {
        parent::__construct($message, 400, $previous);
    }
}
