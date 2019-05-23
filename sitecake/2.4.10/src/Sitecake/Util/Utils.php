<?php
namespace Sitecake\Util;

use Exception;

class Utils
{

    /**
     * Generates unique identifier.
     * @return string
     */
    public static function id()
    {
        return sha1(uniqid('', true));
    }

    public static function map($callback, $arr1, $_ = null)
    {
        $args = func_get_args();
        array_shift($args);
        array_shift($args);
        $res = [];
        $idx = 0;
        foreach ($arr1 as $el) {
            $params = [$el];
            foreach ($args as $arg) {
                array_push($params, $arg[$idx]);
            }
            array_push($res, call_user_func_array($callback, $params));
            $idx++;
        }

        return $res;
    }

    public static function arrayMapProp($array, $property)
    {
        return array_map(function ($el) use ($property) {
            return $el[$property];
        }, $array);
    }

    public static function arrayFindProp($array, $prop, $value)
    {
        return array_shift(Utils::arrayFilterProp($array, $prop, $value));
    }

    public static function arrayFilterProp($array, $property, $value)
    {
        return array_filter($array, function ($el) use ($property, $value) {
            return isset($el[$property]) ?
                ($el[$property] == $value) : false;
        });
    }

    public static function arrayDiff($arr1, $arr2)
    {
        $res = array_diff($arr1, $arr2);

        return is_array($res) ? $res : [];
    }

    public static function iterableToArray($iterable)
    {
        $res = [];
        foreach ($iterable as $item) {
            array_push($res, $item);
        }

        return $res;
    }

    public static function isURL($uri)
    {
        return (preg_match('/^https?:\/\/.*$/', $uri) === 1);
    }

    public static function nameFromURL($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $dot = strrpos($path, '.');
        if ($dot !== false) {
            $path = substr($path, 0, $dot);
        }
        if (strpos($path, '/') === 0) {
            $path = substr($path, 1);
        }

        return preg_replace('/[^0-9a-zA-Z\.\-_]+/', '-', $path);
    }

    /**
     * Creates a resource URL out of the given components.
     *
     * @param  string $path resource path prefix (directory) or full resource path (dir, name, ext)
     * @param  string $name resource name
     * @param  string $id 13-digit resource ID (uniqid)
     * @param  string $subId resource additional id (classifier, subid)
     * @param  string $ext extension
     *
     * @return string        calculated resource path
     */
    public static function resourceUrl($path, $name = null, $id = null, $subId = null, $ext = null)
    {
        $id = ($id == null) ? uniqid() : $id;
        $subId = ($subId == null) ? '' : $subId;
        if ($name == null || $ext == null) {
            $pathInfo = pathinfo($path);
            $name = ($name == null) ? $pathInfo['filename'] : $name;
            $ext = ($ext == null) ? $pathInfo['extension'] : $ext;
            $path = ($pathInfo['dirname'] === '.') ? '' : $pathInfo['dirname'];
        }
        $path = $path . (($path === '' || substr($path, -1) === '/') ? '' : '/');
        $name = str_replace(' ', '_', $name);
        $ext = strtolower($ext);

        return $path . $name . '-sc' . $id . $subId . '.' . $ext;
    }

    /**
     * Checks if the given URL is a Sitecake resource URL.
     *
     * @param  string $url a URL to be tested
     *
     * @return boolean      true if the URL is a Sitecake resource URL
     */
    public static function isScResourceUrl($url)
    {
        $re = '/^.*(files|images)\/.*\-sc[0-9a-f]{13}[^\.]*\..+$/';

        return HtmlUtils::isRelativeURL($url) &&
            !HtmlUtils::isScriptLink($url) &&
            !HtmlUtils::isAnchorLink($url) &&
            preg_match($re, $url);
    }

    public static function isLocalFileUrl($url)
    {
        return HtmlUtils::isRelativeURL($url) &&
            !HtmlUtils::isScriptLink($url) &&
            !HtmlUtils::isAnchorLink($url);
    }

    /**
     * Extracts information from a resource URL.
     * It returns path, name, id, subid and extension.
     *
     * @param $urls
     *
     * @return array URL components (path, name, id, subid, ext) or a list of URL components
     * @internal param array|string $url a URL to be deconstructed or a list of URLs
     *
     */
    public static function resourceUrlInfo($urls)
    {
        if (is_array($urls)) {
            $res = [];
            foreach ($urls as $url) {
                array_push($res, self::__resourceUrlInfo($url));
            }

            return $res;
        } else {
            return self::__resourceUrlInfo($urls);
        }
    }

    private static function __resourceUrlInfo($url)
    {
        preg_match('/((.*)\/)?([^\/]+)-sc([0-9a-fA-F]{13})([^\.]*)\.([^\.]+)$/', $url, $match);

        return [
            'path' => $match[2],
            'name' => $match[3],
            'id' => $match[4],
            'subid' => $match[5],
            'ext' => $match[6]
        ];
    }

    /**
     * Checks if the given URL is a URL to a resource that is not a local HTML page.
     *
     * @param  string $url URL to be checked
     *
     * @return boolean true if the link is a URL to a resource that is not a local HTML page
     */
    public static function isExternalLink($url)
    {
        $hostname = (isset($_SERVER['HTTP_HOST'])) ? $_SERVER['HTTP_HOST'] : exec("hostname");

        return (bool)preg_match('/((http|https):\/\/(?!' . $hostname . ')[\w\.\/\-=?#]+)/', $url);
    }

    /**
     * Returns whether passed class/object has public method by passed name
     *
     * @param string|object $object
     * @param string $method
     *
     * @return bool
     */
    public static function hasPublicMethod($object, $method)
    {
        if (method_exists($object, $method)) {
            try {
                $reflection = new \ReflectionMethod($object, $method);

                return $reflection->isPublic();
            } catch (\Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Converts filesize from human readable string to bytes
     *
     * @param string $size Size in human readable string like '5MB', '5M', '500B', '50kb' etc.
     * @param mixed $default Value to be returned when invalid size was used, for example 'Unknown type'
     *
     * @return mixed Number of bytes as integer on success, `$default` on failure if not false
     * @throws \InvalidArgumentException On invalid Unit type.
     */
    public static function parseFileSize($size, $default = false)
    {
        if (ctype_digit($size)) {
            return (int)$size;
        }
        $size = strtoupper($size);

        $l = -2;
        $i = array_search(substr($size, -2), ['KB', 'MB', 'GB', 'TB', 'PB']);
        if ($i === false) {
            $l = -1;
            $i = array_search(substr($size, -1), ['K', 'M', 'G', 'T', 'P']);
        }
        if ($i !== false) {
            $size = substr($size, 0, $l);

            return $size * pow(1024, $i + 1);
        }

        if (substr($size, -1) === 'B' && ctype_digit(substr($size, 0, -1))) {
            $size = substr($size, 0, -1);

            return (int)$size;
        }

        if ($default !== false) {
            return $default;
        }

        throw new \InvalidArgumentException('No unit type.');
    }

    /**
     * Multibyte preg_match_all method
     *
     * @see http://php.net/manual/en/function.preg-match-all.php
     *
     * @param string $pattern The pattern to search for, as a string.
     * @param string $subject The input string.
     * @param array $matches Array of all matches in multi-dimensional array ordered according to flags.
     * @param int $flags Can be a combination of the following flags (PREG_PATTERN_ORDER, PREG_SET_ORDER,
     *                                 PREG_OFFSET_CAPTURE)
     * @param int $offset Normally, the search starts from the beginning of the subject string. The
     *                                 optional parameter offset can be used to specify the alternate place from which
     *                                 to start the search (in bytes).
     * @param string|null $encoding Encoding
     *
     * @return int
     */
    public static function match(
        $pattern,
        $subject,
        array &$matches = null,
        $flags = PREG_PATTERN_ORDER,
        $offset = 0,
        $encoding = null
    ) {
        // WARNING! - All this function does is to correct offsets, nothing else:
        if (is_null($encoding)) {
            $encoding = mb_internal_encoding();
        }

        $offset = strlen(mb_substr($subject, 0, $offset, $encoding));
        $ret = preg_match_all($pattern, $subject, $matches, $flags, $offset);

        if ($ret && ($flags & PREG_OFFSET_CAPTURE)) {
            foreach ($matches as &$match) {
                foreach ($match as &$match) {
                    if (is_array($match)) {
                        $match[1] = mb_strlen(substr($subject, 0, $match[1]), $encoding);
                    }
                }
            }
        }

        $rt = [];
        for ($z = 0; $z < count($matches); $z++) {
            for ($x = 0; $x < count($matches[$z]); $x++) {
                $rt[$x][$z] = $matches[$z][$x];
            }
        }

        $matches = $rt;

        return $ret;
    }

    /**
     * Formats a stack trace based on the supplied options.
     *
     * ### Options
     *
     * - `depth` - The number of stack frames to return. Defaults to 999
     * - `format` - The format you want the return. Defaults to the currently selected format. If
     *    format is 'array' or 'points' the return will be an array.
     * - `args` - Should arguments for functions be shown?  If true, the arguments for each method call
     *   will be displayed.
     * - `start` - The stack frame to start generating a trace from. Defaults to 0
     *
     * @param array|\Exception $backtrace Trace as array or an exception object.
     * @param array $options Format for outputting stack trace.
     *
     * @return mixed Formatted stack trace.
     */
    public static function formatTrace($backtrace, $options = [])
    {
        if ($backtrace instanceof Exception) {
            $backtrace = $backtrace->getTrace();
        }
        $options = array_merge([
            'depth' => 25,
            'format' => 'array',
            'args' => true,
            'start' => 0,
            'exclude' => ['call_user_func_array', 'trigger_error']
        ], $options);

        $count = count($backtrace);
        $back = [];

        $_trace = [
            'line' => '??',
            'file' => '[internal]',
            'class' => null,
            'function' => '[main]'
        ];

        for ($i = $options['start']; $i < $count && $i < $options['depth']; $i++) {
            $trace = $backtrace[$i] + ['file' => '[internal]', 'line' => '??'];
            $signature = $reference = '[main]';

            if (isset($backtrace[$i + 1])) {
                $next = $backtrace[$i + 1] + $_trace;
                $signature = $reference = $next['function'];

                if (!empty($next['class'])) {
                    $signature = $next['class'] . '::' . $next['function'];
                    $reference = $signature . '(';
                    if ($options['args'] && isset($next['args'])) {
                        $args = [];
                        foreach ($next['args'] as $arg) {
                            $args[] = self::exportVar($arg);
                        }
                        $reference .= implode(', ', $args);
                    }
                    $reference .= ')';
                }
            }
            if (in_array($signature, $options['exclude'])) {
                continue;
            }
            if ($options['format'] === 'array') {
                $trace['args'] = $reference;
                $trace = array_map(function ($element) {
                    if (is_string($element)) {
                        return utf8_encode($element);
                    }

                    return $element;
                }, $trace);
                $back[] = $trace;
            } else {
                $back[] = utf8_encode(sprintf('%s - %s, line %s', $reference, $trace['file'], $trace['line']));
            }
        }

        if ($options['format'] === 'array') {
            return $back;
        }

        return implode("\n", $back);
    }

    /**
     * Protected export function used to keep track of indentation and recursion.
     *
     * @param mixed $var The variable to dump.
     *
     * @return string The dumped variable.
     */
    protected static function exportVar($var)
    {
        switch (static::getType($var)) {
            case 'boolean':
                return ($var) ? 'true' : 'false';
            case 'integer':
                return '(int) ' . $var;
            case 'float':
                return '(float) ' . $var;
            case 'string':
                if (trim($var) === '') {
                    return "''";
                }

                return "'" . $var . "'";
            case 'array':
                return '(array)';
            case 'resource':
                return strtolower(gettype($var));
            case 'null':
                return 'null';
            case 'unknown':
                return 'unknown';
            default:
                return '(object)';
        }
    }

    /**
     * Get the type of the given variable. Will return the class name
     * for objects.
     *
     * @param mixed $var The variable to read the type of.
     *
     * @return string The type of variable.
     */
    public static function getType($var)
    {
        if (is_object($var)) {
            return get_class($var);
        }
        if ($var === null) {
            return 'null';
        }
        if (is_string($var)) {
            return 'string';
        }
        if (is_array($var)) {
            return 'array';
        }
        if (is_int($var)) {
            return 'integer';
        }
        if (is_bool($var)) {
            return 'boolean';
        }
        if (is_float($var)) {
            return 'float';
        }
        if (is_resource($var)) {
            return 'resource';
        }

        return 'unknown';
    }

    /**
     * Renders/returns current time parsed to H:i:s.miliseconds
     *
     * @param string $text Optional. Text to prefix time if needs to be rendered. Empty by default
     * @param bool   $echo Optional. Indicates whether time needs to be returned or rendered. Render by default
     *
     * @return string
     */
    public static function getTime($text = '', $echo = true)
    {
        list($microSeconds, $seconds) = explode(' ', microtime());
        //$microSeconds = str_replace("0.", "", $microSeconds);
        $time = date('H:i:s', $seconds) . '.' . round($microSeconds*1000);
        if ($echo) {
            if ($text) {
                $time = '<strong>' . $text . '</strong> - ' . $time;
            }
            echo '<pre style="background: #a3e4a5;border-radius:5px;margin:10px;padding:15px;">' . $time . '</pre>';
        }

        return $time;
    }
}
