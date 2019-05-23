<?php

namespace Sitecake\Exception;

class MissingActionException extends Exception
{
    protected $messageTemplate = 'Action %s not implemented for service %s';
}
