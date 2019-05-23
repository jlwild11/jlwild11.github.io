<?php

namespace Sitecake\Exception;

class BadFormatException extends Exception
{
    protected $messageTemplate = 'SiteCake container (.%s) is not contained inside one file.';
}
