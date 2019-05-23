<?php

namespace Sitecake\Log\Engine;

use JsonSerializable;
use Psr\Log\AbstractLogger;

abstract class BaseLog extends AbstractLogger
{
    /**
     * Converts to string the provided data so it can be logged.
     * Method interpolate variables or add additional info to the logged message from passed context.
     *
     * @param mixed $data The data to be converted to string and logged.
     * @param array $context Additional logging information for the message.
     *
     * @return string
     */
    protected function format($data, array $context = [])
    {
        if (is_string($data)) {
            return $this->interpolate($data, $context);
        }

        $object = is_object($data);

        if ($object && method_exists($data, '__toString')) {
            return (string)$data;
        }

        if ($object && $data instanceof JsonSerializable) {
            return json_encode($data);
        }

        return print_r($data, true);
    }

    /**
     * Interpolates context values into the message placeholders.
     *
     * @param string $message
     * @param array $context
     *
     * @return string Interpolated message
     */
    protected function interpolate($message, $context = [])
    {
        // Build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = print_r($val, true);
        }

        // Interpolate replacement values into the message and return
        return strtr($message, $replace);
    }
}
