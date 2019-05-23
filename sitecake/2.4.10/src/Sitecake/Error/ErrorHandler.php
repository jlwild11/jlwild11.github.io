<?php

namespace Sitecake\Error;

use Monolog\Formatter\FormatterInterface;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ErrorHandler implements EventSubscriberInterface
{
    const SUPPRESS_NONE = 0;

    const SUPPRESS_NEXT = 1;

    const SUPPRESS_ALL = 2;

    /**
     * @var LoggerInterface[]
     */
    protected static $loggers = [];

    /**
     * @var FormatterInterface[]
     */
    protected static $formatters = [];

    /**
     * @var ErrorHandler
     */
    protected static $handler;

    protected static $suppressed = self::SUPPRESS_NONE;

    protected $debug = false;

    public function __construct($debug = false)
    {
        $this->debug = $debug;
    }

    public static function register($level, LoggerInterface $logger = null, FormatterInterface $formatter = null)
    {
        if (!empty($logger)) {
            self::$loggers[] = $logger;
        }

        if (!empty($formatter)) {
            self::$formatters[] = $formatter;
        }

        self::$handler = new static();

        // Set error reporting level
        error_reporting($level);
        // Set error handler
        set_error_handler([self::$handler, 'handleError'], $level);
        // Set Exception handler
        set_exception_handler([self::$handler, 'wrapAndHandleException']);
        //@ini_set('display_errors', 'Off');
        //@ini_set('display_warnings', 'Off');

        // Register shutdown function
        register_shutdown_function(function () {
            $error = error_get_last();
            if (!is_array($error)) {
                return;
            }

            $fatalErrors = [
                E_USER_ERROR,
                E_ERROR,
                E_PARSE,
            ];
            if (in_array($error['type'], $fatalErrors, true)) {
                self::$handler->handleFatalError(
                    $error['type'],
                    $error['message'],
                    $error['file'],
                    $error['line']
                );
            }
        });

        return self::$handler;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [KernelEvents::EXCEPTION => ['onSilexError', -255]];
    }

    /**
     * Handles silex error
     *
     * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
     *
     * @throws \Exception
     */
    public function onSilexError(GetResponseForExceptionEvent $event)
    {
        $this->wrapAndHandleException($event->getException());
    }

    /**
     * Sets debug mode
     *
     * @param bool $debug
     *
     * @return $this
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * Display/Log a fatal error.
     *
     * @param int $code Code of error
     * @param string $description Error description
     * @param string $file File on which error occurred
     * @param int $line Line that triggered the error
     * @param mixed $context Context
     *
     * @return bool
     */
    public function handleFatalError($code, $description, $file, $line, $context = null)
    {
        http_response_code(500);

        $data = [
            'level' => 'error',
            'level_name' => 'Error',
            'code' => $code,
            'description' => $description,
            'file' => $file,
            'line' => $line,
            'error' => 'Fatal Error',
            'context' => (array)$context
        ];

        if (!empty($context)) {
            $data['context'] = $context;
        }

        self::$handler->logError('error', $data);

        self::$handler->displayError($data);

        return true;
    }

    /**
     * Checks the passed exception type. If it is an instance of `Error`
     * then, it wraps the passed object inside another Exception object
     * for backwards compatibility purposes.
     *
     * @param \Exception|\Error $exception The exception to handle
     *
     * @return void
     * @throws \Exception
     */
    public function wrapAndHandleException($exception)
    {
        if ($exception instanceof \Error) {
            $exception = new PHP7ErrorException($exception);
        }
        self::$handler->handleException($exception);
    }

    /**
     * Log an error.
     *
     * @param string|\Exception $level The level name of the log.
     * @param array $data Array of error data.
     *
     * @return bool
     */
    protected function logError($level, $data = [])
    {
        if ($level instanceof \Exception) {
            $message = self::getMessage($level);
            $level = 'error';
        } else {
            $message = sprintf(
                '%s (%s): %s in [%s, line %s]',
                $data['error'],
                $data['code'],
                $data['description'],
                $data['file'],
                $data['line']
            );

            ob_start();
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $trace = ob_get_contents();
            ob_end_clean();

            $message .= "\nStack Trace:\n" . print_r($trace, true) . "\n\n";
        }

        foreach (self::$loggers as $logger) {
            $logger->log($level, $message, isset($data['context']) ? $data['context'] : []);
        }

        return true;
    }

    /**
     * Generates a formatted error message
     *
     * @param \Exception $exception Exception instance
     *
     * @return string Formatted message
     */
    protected static function getMessage(\Exception $exception)
    {
        return $message = sprintf(
                              '%s (%s): %s in [%s, line %s]',
                              get_class($exception),
                              $exception->getCode(),
                              $exception->getMessage(),
                              $exception->getFile(),
                              $exception->getLine()
                          ) . "\nStack Trace:\n" . $exception->getTraceAsString() . "\n\n";
    }

    /**
     * Depending on second parameter method displays or returns formatted error message if it's not suppressed
     *
     * @param      $error
     * @param bool $return
     *
     * @return string|null
     */
    protected function displayError($error, $return = false)
    {
        if (self::$suppressed != self::SUPPRESS_NONE && !$return) {
            if (self::$suppressed == self::SUPPRESS_NEXT) {
                self::$suppressed = self::SUPPRESS_NONE;
            }

            return '';
        }

        if (!$this->debug) {
            return '';
        }

        if (PHP_SAPI == 'cli') {
            return var_dump($error);
        }

        $error = self::$handler->format($error);

        /*$traceStr =
            "<pre class=\"sitecake-error\" style=\"background: #E4E4E4;border-radius:5px;margin:10px;padding:15px;\">";
        $traceStr .= sprintf(
            '<p style="margin:0;">' .
            '<strong>%s:</strong> %s ' .
            '<strong>in file</strong> %s ' .
            '<strong>on line</strong> (#%s)' .
            '</p>',
            $error['error'],
            $error['description'],
            $error['file'],
            $error['line']
        );

        $trace = debug_backtrace();
        $traceStr .= "<div>";
        for($i = 0; $i < count($trace); $i++)
        {
            $traceStr .= '#' . $i . ' ';
            if($trace[$i]['type'])
            {
                $traceStr .= $trace[$i]['class'] . $trace[$i]['type'] . $trace[$i]['function'];
            }
            else
            {
                $traceStr .= $trace[$i]['function'];
            }
            $traceStr .= sprintf('() - %s, line %s', $trace[$i]['file'], $trace[$i]['line']) . '<br />';
        }

        $traceStr .= "</div>";

        $traceStr .= "</pre>";*/

        if ($return) {
            return $error;
        }

        if ($this->debug) {
            echo $error;
        }

        return null;
    }

    /**
     * Apply registered formatters to passed error
     *
     * @param array $error
     *
     * @return mixed
     */
    protected function format($error)
    {
        foreach (self::$formatters as $formatter) {
            $error = $formatter->format($error);
        }

        return $error;
    }

    /**
     * Set as the default error handler by Sitecake.
     *
     * This function will log errors to Log, when debug == false.
     *
     * @param int $code Code of error
     * @param string $description Error description
     * @param string|null $file File on which error occurred
     * @param int|null $line Line that triggered the error
     * @param array|null $context Context
     *
     * @return bool True if error was handled
     */
    public function handleError($code, $description, $file = null, $line = null, $context = null)
    {
        if (error_reporting() === 0) {
            return false;
        }

        list($error, $log) = self::mapErrorCode($code);
        if ($log === LOG_ERR) {
            return self::$handler->handleFatalError($code, $description, $file, $line, $context);
        }
        $data = [
            'level_name' => ucfirst($error),
            'level' => $log,
            'code' => $code,
            'error' => ucfirst($error),
            'description' => $description,
            'file' => $file,
            'line' => $line,
            'extra' => [],
            'context' => $context
        ];

        if (!empty($context)) {
            $data['context'] = $context;
        }

        self::$handler->logError($error, $data);

        self::$handler->displayError($data);

        return true;
    }

    /**
     * Map an error code into an Error word, and log location.
     *
     * @param int $code Error code to map
     *
     * @return array Array of error word, and log location.
     */
    protected static function mapErrorCode($code)
    {
        $levelMap = [
            E_PARSE => 'error',
            E_ERROR => 'error',
            E_CORE_ERROR => 'error',
            E_COMPILE_ERROR => 'error',
            E_USER_ERROR => 'error',
            E_WARNING => 'warning',
            E_USER_WARNING => 'warning',
            E_COMPILE_WARNING => 'warning',
            E_RECOVERABLE_ERROR => 'warning',
            E_NOTICE => 'notice',
            E_USER_NOTICE => 'notice',
            E_STRICT => 'strict',
            E_DEPRECATED => 'deprecated',
            E_USER_DEPRECATED => 'deprecated',
        ];
        $logMap = [
            'error' => Logger::ERROR,
            'warning' => Logger::WARNING,
            'notice' => Logger::NOTICE,
            'strict' => Logger::NOTICE,
            'deprecated' => Logger::NOTICE,
        ];

        $error = $levelMap[$code];
        $log = $logMap[$error];

        return [$error, $log];
    }

    /**
     * Handle uncaught exceptions.
     *
     * Uses a template method provided by subclasses to display errors in an
     * environment appropriate way.
     *
     * @param \Exception $exception Exception instance.
     * @param bool $return Weather exception string should be returned or displayed. Displayed by default
     *
     * @return string|null Formatted error message if $return is true, null otherwise
     * @see http://php.net/manual/en/function.set-exception-handler.php
     */
    public function handleException(\Exception $exception, $return = false)
    {
        $trace = $exception->getTrace();
        $error = get_class($exception);
        $data = [
            'level' => 'error',
            'level_name' => $error,
            'function' => $trace[0]['function'] ? $trace[0]['function'] : false,
            'class' => $trace[0]['class'] ? $trace[0]['class'] : false,
            'type' => $trace[0]['type'] ? $trace[0]['type'] : false,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'description' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'error' => $error,
            'extra' => [],
            'context' => []
        ];

        self::$handler->logError($exception);

        if ($return == true) {
            return self::displayError($data, true);
        }

        self::$handler->displayError($data, $return);

        return null;
    }

    /**
     * Suppresses displaying of next or all error messages
     *
     * @param int $level Level of suppression.
     *                   Indicates weather all or just next message should be suppressed for displaying.
     */
    public static function suppress($level = self::SUPPRESS_NEXT)
    {
        self::$suppressed = $level;
    }
}
