<?php

namespace Sitecake\Exception\Http;

use Sitecake\Exception\Exception;

class NotFoundException extends Exception
{
    protected $messageTemplate = '404 Not Found : %s';

    public function __construct($message, $code = 404, $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
