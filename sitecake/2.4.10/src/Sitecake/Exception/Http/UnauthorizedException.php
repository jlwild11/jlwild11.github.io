<?php

namespace Sitecake\Exception\Http;

use Sitecake\Exception\Exception;

class UnauthorizedException extends Exception
{
    protected $messageTemplate = '401 Unauthorized : %s';

    public function __construct($message, $code = 401, $previous = null)
    {
        parent::__construct($message, 401, $previous);
    }
}
