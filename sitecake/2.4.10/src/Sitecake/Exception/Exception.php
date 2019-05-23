<?php

namespace Sitecake\Exception;

use RuntimeException;

class Exception extends RuntimeException
{
    /**
     * @var array Message attributes
     */
    protected $attributes;

    /**
     * @var string|array Message template used if extra parameters needs to be mentioned in exception message
     */
    protected $messageTemplate;

    /**
     * Exception constructor.
     *
     * @param string|array $message Exception message
     * @param int          $code    Exception code
     * @param null         $previous
     */
    public function __construct($message, $code = 500, $previous = null)
    {
        if (is_array($message)) {
            $this->attributes = $message;
            $message = vsprintf($this->messageTemplate, $message);
        } elseif (is_string($message) && $this->messageTemplate) {
            $message = sprintf($this->messageTemplate, $message);
        }

        parent::__construct($message, $code, $previous);
    }
}
