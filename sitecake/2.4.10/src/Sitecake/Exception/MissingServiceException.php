<?php

namespace Sitecake\Exception;

class MissingServiceException extends Exception
{
    protected $messageTemplate = 'Service %s could not be found';
}
