<?php

namespace Sitecake\Exception;

class UnexistingMethodException extends Exception
{
    protected $messageTemplate = 'Method %s doesn\'t exist in class %s';
}
