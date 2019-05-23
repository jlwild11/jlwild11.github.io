<?php

namespace Sitecake\Exception;

class MissingArgumentsException extends Exception
{
    protected $messageTemplate = 'Argument \'%s\' not passed in request.';
}
