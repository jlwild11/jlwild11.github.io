<?php

namespace Sitecake\Api\Exception;

use Sitecake\Exception\Exception;

class InvalidPluginDefinitionException extends Exception
{
    protected $messageTemplate = 'Sitecake plugin "%s" doesn\'t implement PluginInterface';
}
