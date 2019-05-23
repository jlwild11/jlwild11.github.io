<?php

namespace Sitecake\Resources\Exception;

use Sitecake\Exception\Exception;

class ResourceNotFoundException extends Exception
{
    protected $messageTemplate = '%s resource "%s" not found';
}
