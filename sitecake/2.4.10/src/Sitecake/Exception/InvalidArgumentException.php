<?php

namespace Sitecake\Exception;

class InvalidArgumentException extends Exception
{
    protected $messageTemplate = 'Argument \'%s\' is not formatted right.';
}
