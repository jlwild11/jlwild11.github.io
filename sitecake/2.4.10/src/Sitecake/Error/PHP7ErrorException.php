<?php

namespace Sitecake\Error;

use Exception;

/**
 * Wraps a PHP 7 Error object inside a normal Exception
 * so it can be handled correctly by the rest of the
 * error handling system
 *
 */
class PHP7ErrorException extends Exception
{

    /**
     * The wrapped error object
     *
     * @var \Error
     */
    protected $originalError;

    /**
     * Wraps the passed Error class
     *
     * @param \Error $error the Error object
     */
    public function __construct($error)
    {
        $this->originalError = $error;
        $this->message = $error->getMessage();
        $this->code = $error->getCode();
        $this->file = $error->getFile();
        $this->line = $error->getLine();
        $msg = sprintf(
            '%s (%s): %s in [%s, line %s]',
            get_class($error),
            $this->code,
            $this->message,
            $this->file ?: 'null',
            $this->line ?: 'null'
        );
        parent::__construct($msg, $this->code, $error->getPrevious());
    }

    /**
     * Returns the wrapped error object
     *
     * @return \Error
     */
    public function getError()
    {
        return $this->originalError;
    }
}
