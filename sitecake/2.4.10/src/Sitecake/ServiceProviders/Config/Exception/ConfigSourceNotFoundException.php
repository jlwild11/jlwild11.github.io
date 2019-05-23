<?php

namespace Sitecake\Exception;

class ConfigSourceNotFoundException extends Exception
{
    protected $messageTemplate = 'Configuration source "%s" not found';
}
