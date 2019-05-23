<?php

namespace Sitecake\Api\Exception;

use Sitecake\Exception\Exception;

class MissingPluginException extends Exception
{
    protected $messageTemplate = 'Plugin class \'%s\' not found.';
}
