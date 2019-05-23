<?php

namespace Sitecake\Log;

use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class Log
{
    /**
     * Log engine
     * @var LoggerInterface
     */
    protected static $logger;

    /**
     * Handled log levels
     *
     * @var array
     */
    protected static $levels = [
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug'
    ];

    /**
     * Log levels as detailed in RFC 5424
     *
     * @var array
     */
    protected static $levelMap = [
        'emergency' => LOG_EMERG,
        'alert' => LOG_ALERT,
        'critical' => LOG_CRIT,
        'error' => LOG_ERR,
        'warning' => LOG_WARNING,
        'notice' => LOG_NOTICE,
        'info' => LOG_INFO,
        'debug' => LOG_DEBUG,
    ];

    public static function init($logger)
    {
        static::$logger = $logger;
    }

    /**
     * @param string $level
     * @param string $message
     * @param array $context
     *
     * @return bool|null
     */
    public static function write($level, $message, $context = [])
    {
        if (!static::$logger) {
            return null;
        }

        if (is_numeric($level) && in_array($level, static::$levelMap)) {
            $level = array_search($level, static::$levelMap);
        }

        if (!in_array($level, static::$levels)) {
            throw new InvalidArgumentException(sprintf('Invalid log level "%s"', $level));
        }

        if (!method_exists(static::$logger, 'log')) {
            throw new \LogicException('Logger have to implement method \'log\'');
        }

        static::$logger->log($level, $message, $context);

        return true;
    }
}
