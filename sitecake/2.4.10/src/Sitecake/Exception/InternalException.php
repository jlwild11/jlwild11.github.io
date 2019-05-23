<?php

namespace Sitecake\Exception;

class InternalException extends Exception
{
    protected $messageTemplate = 'SiteCake Internal Server Error : %s';
}
