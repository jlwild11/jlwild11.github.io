<?php

namespace Sitecake\Util;

use Gajus\Dindent\Indenter;

class Beautifier
{
    protected static $config = [];

    protected static $indenter;

    const INDENT = "\t";

    const PATTERN_OPENING_TAG = '/<([^\s>\/]+)/';

    const PATTERN_CLOSING_TAG = '/<\/([^>]*)>/';

    public static function config($options)
    {
        self::$config = array_merge(['indentation_character' => self::INDENT], $options);
    }

    /**
     * Indents passed HTML input string based on options and passed params
     *
     * @param        $input
     * @param string $prefixIndentation
     * @param bool   $skipFirstLine
     *
     * @return string
     * @throws \Gajus\Dindent\Exception\InvalidArgumentException
     * @throws \Gajus\Dindent\Exception\RuntimeException
     */
    public static function indent($input, $prefixIndentation = '', $skipFirstLine = false)
    {
        if (!isset(self::$indenter)) {
            self::$indenter = new Indenter(self::$config);
        }

        // Fix tags
        $output = self::fixTags($input);

        // Indent
        $output = self::$indenter->indent($output);

        // Add prefix to all except first line if specified
        $prefixed = '';
        $lines = preg_split('/\r\n|\n|\r/', $output);
        $lines = array_filter($lines);
        foreach ($lines as $no => $line) {
            if ($skipFirstLine && $no == 0) {
                $prefixed .= $line . "\n";
            } else {
                $prefixed .= (string)$prefixIndentation . $line . ($no < count($lines) - 1 ? "\n" : "");
            }
        }

        return $prefixed;
    }

    public static function fixTags($input)
    {
        // Remove space before closing of opening tag
        $input = preg_replace_callback('/(<[^\/][^>]*)(\s>)/', function ($matches) {
            return $matches[1] . '>';
        }, $input);

        // Fix opening tags
        if (preg_match_all(self::PATTERN_OPENING_TAG, $input, $matches)) {
            $matches = array_unique($matches[0]);

            foreach ($matches as $match) {
                // Lowercase opening tags except <!DOCTYPE>
                if ($match !== '<!DOCTYPE') {
                    $input = str_replace($match, strtolower($match), $input);
                }
            }
        }

        // Fix closing tags
        if (preg_match_all(self::PATTERN_CLOSING_TAG, $input, $matches)) {
            $matches = array_unique($matches[0]);

            foreach ($matches as $match) {
                // Lowercase closing tags
                $input = str_replace($match, strtolower($match), $input);
            }
        }

        return $input;
    }
}
