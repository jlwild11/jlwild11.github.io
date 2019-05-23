<?php

namespace Sitecake\PageManager\Exception;

use Sitecake\Exception\Exception;

class TemplateNotFoundException extends Exception
{
    /**
     * {@internal }
     */
    protected $messageTemplate = 'Template %s not found';

    /**
     * PageNotFoundException constructor.
     *
     * {@inheritdoc}
     */
    public function __construct($message, $code = 401, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
