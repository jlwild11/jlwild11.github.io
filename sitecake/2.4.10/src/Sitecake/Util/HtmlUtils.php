<?php

namespace Sitecake\Util;

class HtmlUtils
{
    /**
     * Appends passed HTML code to passed DOM node
     *
     * @param \DOMNode $parent
     * @param string   $html
     */
    public static function appendHTML(\DOMNode $parent, $html)
    {
        $tmpDoc = new \DOMDocument();

        // Suppress HTML5 errors
        libxml_use_internal_errors(true);
        $tmpDoc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_use_internal_errors(false);

        foreach ($tmpDoc->getElementsByTagName('body')->item(0)->childNodes as $node) {
            $node = $parent->ownerDocument->importNode($node, true);
            $parent->appendChild($node);
        }
    }

    /**
     * Tries to add passed HTML code to the HTML page head section and returns insertion position. False otherwise.
     *
     * @param  string $html   HTML source
     * @param  string $code   Code to add
     * @param  bool   $append [optional] Indicates whether passed code should be appended or prepended to head tag.
     *               Method will try both append and prepend operations, but this parameter indicates what to try first.
     *               If opening or closing head tag are not found, method tries insertion based on title tag if found
     *
     * @return bool
     */
    public static function addCodeToHead(&$html, $code, $append = true)
    {
        // Try to find head tag to insert passed code.
        if (Utils::match(
            '/<head(?:\s[^>]+>|>)|<title[^>]*>|<\/head>|<\/title>/',
            $html,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            $index = -1;
            if ($append) {
                $matches = array_reverse($matches);
            }
            foreach ($matches as $no => $match) {
                if ($append && ($match[0][0] == '</head>' || $match[0][0] == '</title>')) {
                    $index = $no;
                    break;
                } elseif (!$append && ($match[0][0] == '<head>' || $match[0][0] == '<title>')) {
                    $index = $no;
                    break;
                }
            }
            if ($index === -1) {
                $index = 0;
            }
            $tag = $matches[$index][0];
            if ($tag[0] == '</head>' || strpos($tag[0], '<title') === 0) {
                // Closing head tag or opening title tag found. Insert passed HTML before it
                $html = mb_substr($html, 0, $tag[1]) .
                    $code . "\n" . mb_substr($html, $tag[1]);
                $insertionPosition = $tag[1];
            } else {
                // Opening head tag or closing title tag found. Insert passed HTML right after it
                $insertionPosition = $tag[1] + strlen($tag[0]);
                $html = mb_substr($html, 0, $insertionPosition) .
                    "\n" . $code . mb_substr($html, $insertionPosition);
            }

            return $insertionPosition;
        }

        return false;
    }

    /**
     * Inserts passed code before closing body tag of passed HTML and returns inserting position
     *
     * @param string $html Html source
     * @param string $code Code to insert
     *
     * @return bool|int
     */
    public static function appendCodeToBody(&$html, $code)
    {
        // Try to find closing body tag to insert passed code.
        if (Utils::match(
            '/<\/body>/',
            $html,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            if (isset($matches[0][0])) {
                $position = $matches[0][0][1];
                $html = mb_substr($html, 0, $position) .
                    $code . "\n" . mb_substr($html, $position);

                return $position;
            }
        }

        return false;
    }

    /**
     * Returns a DOMDocument created of passed HTML code.
     *
     * @param  string $html HTML code
     *
     * @return \DOMDocument    the resulting phpQueryObject instance
     */
    public static function htmlToDocument($html)
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_use_internal_errors(false);

        return $dom;
    }

    /**
     * Returns DOMNode created of passed HTML code
     *
     * @param string $html HTML code
     *
     * @return \DOMElement
     */
    public static function htmlToDOMElement($html)
    {
        $doc = new \DOMDocument();
        // Suppress HTML5 errors
        libxml_use_internal_errors(true);
        $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_use_internal_errors(false);

        $baseElements = ['html', 'head', 'body'];
        $headElements = ['title', 'base', 'link', 'meta', 'script', 'style'];

        // Base elements should be accessed directly
        if (preg_match('/^<(' . implode('|', $baseElements) . ')(\s|>)/', $html, $matches)) {
            return $doc->documentElement->getElementsByTagName($matches[1])->item(0);
            // Head elements should be accessed through head
        } elseif (preg_match('/^<(' . implode('|', $headElements) . ')(\s|>)/', $html, $matches)) {
            return $doc->documentElement->getElementsByTagName('head')->item(0)->childNodes->item(0);
            // All other elements will be appended to body
        } else {
            return $doc->documentElement
                ->getElementsByTagName('body')
                ->item(0)->childNodes
                ->item(0);
        }
    }

    /**
     * Returns a <script> html tag for loading JavaScript code from the given URL.
     *
     * @param  string $url        the tag src attribute
     * @param  array  $attributes attributes to render for rendering script tag
     *
     * @return string      the result script tag
     */
    public static function script($url, $attributes = [])
    {
        $attributes += [
            'type' => 'text/javascript',
            'language' => 'javascript'
        ];

        $attributes['src'] = $url;

        $attributeKeyPairs = [];

        foreach ($attributes as $attribute => $value) {
            $attributeKeyPairs[] = $attribute . '="' . $value . '"';
        }

        return '<script ' . implode(' ', $attributeKeyPairs) . '></script>';
    }

    /**
     * Wraps the given JavaScript code with a <script> tag.
     *
     * @param  string $source JavaScript code to be wrapped
     * @param  array  $attributes attributes to render for rendering script tag
     *
     * @return string       the wrapped code
     */
    public static function inlineScript($source, $attributes = [])
    {
        $attributes += [
            'type' => 'text/javascript',
            'language' => 'javascript'
        ];

        $attributeKeyPairs = [];

        foreach ($attributes as $attribute => $value) {
            $attributeKeyPairs[] = $attribute . '="' . $value . '"';
        }

        $script = '<script ' . implode(' ', $attributeKeyPairs) . '>';
        $script .= $source;
        $script .= '</script>';

        return $script;
    }

    /**
     * Returns a <link type="text/css"> html tag for loading CSS file from the given URL.
     *
     * @param  string $url        the tag href attribute
     * @param  array  $attributes attributes to render for rendering script tag
     *
     * @return string      the result script tag
     */
    public static function css($url, $attributes = [])
    {
        $attributes += [
            'type' => 'text/css',
            'rel' => 'stylesheet',
            'media' => 'all'
        ];

        $attributes['href'] = $url;

        $attributeKeyPairs = [];

        foreach ($attributes as $attribute => $value) {
            $attributeKeyPairs[] = $attribute . '="' . $value . '"';
        }

        return '<link ' . implode(' ', $attributeKeyPairs) . '>';
    }

    /**
     * Wraps the given CSS code with a <style> tag.
     *
     * @param  string $source CSS code to be wrapped
     *
     * @return string       The wrapped code
     */
    public static function inlineStyle($source)
    {
        $style = '<style>';
        $style .= $source;
        $style .= '</style>';

        return $style;
    }

    /**
     * Tests if the given URL is an absolute URL.
     *
     * @param  string $url an URL to be tested
     *
     * @return boolean      the test result
     */
    public static function isAbsoluteURL($url)
    {
        return (strpos($url, 'http://') === 0) || (strpos($url, 'https://') === 0);
    }

    /**
     * Tests if a given URL is script URL (javascript, tel, email)
     *
     * @param string $url an URL to be tested
     *
     * @return boolean the test result
     */
    public static function isScriptLink($url)
    {
        return (strpos($url, 'javascript:') === 0)
            || (strpos($url, 'mailto:') === 0)
            || (strpos($url, 'tel:') === 0);
    }

    /**
     * Tests if a given URL is anchor (starts with #)
     *
     * @param string $url an URL to be tested
     *
     * @return boolean the test result
     */
    public static function isAnchorLink($url)
    {
        return (strpos($url, '#') === 0);
    }

    /**
     * Prefixes all given node attributes with the specified value.
     *
     * @see HtmlUtils::prefixNodeAttribute
     *
     * @param  \DOMElement  $node       [description]
     * @param  string|array $attributes attribute name, comma-separated list or array of attribute names
     * @param  string       $prefix     a value to prefix the attribute with
     * @param bool|callable $test       change condition function
     *
     * @return \DOMNode the input node reference
     */
    public static function prefixNodeAttributes($node, $attributes, $prefix, $test = false)
    {
        $attributes = is_string($attributes) ? explode(",", $attributes) : $attributes;
        foreach ($attributes as $attr) {
            self::prefixNodeAttribute($node, trim($attr), $prefix, $test);
        }

        return $node;
    }

    /**
     * Prefix the given node's attribute with a prefix if its value satisfies
     * the test. In case the test is not provided, *HtmlUtils::isRelativeURL*
     * will be used.
     *
     * @param  \DOMElement  $node         reference to a DOMNode
     * @param  string       $attribute    name of a node attribute
     * @param  string       $prefix       a string value that the attr value would be prefixed with
     * @param bool|callable $test         a test function (callable) that tests
     *                                    if the provided attr value should be modified by returning
     *                                    a boolean value
     *
     * @return \DOMNode the input node reference
     */
    public static function prefixNodeAttribute($node, $attribute, $prefix, $test = false)
    {
        if ($node->hasAttribute($attribute)) {
            $val = $node->getAttribute($attribute);
            $val = preg_replace_callback(
                '/([^\s,]+)(\s?[^,]*)/',
                function ($match) use ($prefix, $test) {
                    $shouldPrefix = is_callable($test) ? $test($match[1]) : self::isRelativeURL($match[1]);

                    return ($shouldPrefix ? $prefix : '') . $match[1] . $match[2];
                },
                $val
            );
            $node->setAttribute($attribute, $val);
        }

        return $node;
    }

    /**
     * Tests if the given URL is an relative URL.
     *
     * @param string $url an URL to be tested
     *
     * @return boolean the test result
     */
    public static function isRelativeURL($url)
    {
        return !((strpos($url, 'http://') === 0) || (strpos($url, 'https://') === 0));
    }

    /**
     * Removes the given prefix from all specified node attributes.
     *
     * @see HtmlUtils::unPrefixNodeAttribute
     *
     * @param  \DOMElement  $node         reference to a DOMNode node
     * @param  string|array $attributes   attribute name, a comma-separated list or an array of attribute names
     * @param  string       $prefix       a prefix that should be stripped from the beginning of the attr value
     * @param bool|callable $test         a test function (callable) that controls if
     *                                    the attribute value should be modified by returning a boolean value
     *
     * @return \DOMNode the input node reference
     */
    public static function unPrefixNodeAttributes($node, $attributes, $prefix, $test = false)
    {
        $attributes = is_string($attributes) ? explode(",", $attributes) : $attributes;
        foreach ($attributes as $attr) {
            self::unPrefixNodeAttribute($node, trim($attr), $prefix, $test);
        }

        return $node;
    }

    /**
     * Removes the given prefix from the give node's attribute if the attribute
     * value starts with the prefix and if the provided test function returns *true*.
     *
     * @param  \DOMElement  $node         reference to a DOMNode node
     * @param  string       $attr         a node attribute name
     * @param  string       $prefix       a prefix that should be stripped from the beginning of the attr value
     * @param bool|callable $test         a test function (callable) that controls if
     *                                    the attribute value should be modified by returning a boolean value
     *
     * @return \DOMNode the input node reference
     */
    public static function unPrefixNodeAttribute($node, $attr, $prefix, $test = false)
    {
        if ($node->hasAttribute($attr)) {
            $val = $node->getAttribute($attr);
            $val = preg_replace_callback(
                '/([^\s,]+)(\s?[^,]*)/',
                function ($match) use ($prefix, $test) {
                    $shouldUnPrefix = (strpos($match[1], $prefix) === 0)
                        && (is_callable($test) ? $test($match[1]) : true);

                    return ($shouldUnPrefix ? substr($match[1], strlen($prefix)) : $match[1]) . $match[2];
                },
                $val
            );
            $node->setAttribute($attr, $val);
        }

        return $node;
    }

    /**
     * Evaluates passed content that is mixture of HTML and PHP code
     *
     * @param string $source Content to evaluate
     *
     * @return string
     */
    public static function evaluate($source)
    {
        ob_start();
        eval('?>' . $source);
        $result = ob_get_contents();
        ob_end_clean();

        return $result;
    }
}
