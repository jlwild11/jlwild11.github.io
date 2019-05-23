<?php

namespace Sitecake\Exception;

class FileNotFoundException extends Exception
{
    protected $messageTemplate = '%s file "%s" not found';
}
