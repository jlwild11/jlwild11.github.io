<?php

namespace Sitecake\Util;

class Text
{
    /**
     * Converts a file name into a UTF-8 version regarding the PHP OS.
     *
     * @param  string $filename String to be converted
     * @param  string $default  [optional] String to be used if sanitized string is empty
     * @param  string $platform [optional] An optional string to define the OS platform. By default it's PHP_OS.
     *
     * @return string           converted filename
     */
    public static function sanitizeFilename($filename, $default = '', $platform = PHP_OS)
    {
        $filename = self::slug($filename, ['transliterate' => true]);

        if ('WIN' == substr($platform, 0, 3)) {
            $outputCharset = 'UTF-8';
        } else {
            $outputCharset = 'Windows-1251';
        }

        $charset = 'UTF-8';

        if (function_exists('iconv')) {
            $filename = iconv($charset, $outputCharset . '//TRANSLIT//IGNORE', $filename);
        } elseif (function_exists('mb_convert_encoding')) {
            $filename = mb_convert_encoding($filename, $charset, $outputCharset);
        }

        // Remove unwanted characters
        $filename = preg_replace('~[^-\w\.]+~', '', $filename);
        // Trim ending dots (for security reasons and win compatibility)
        $filename = preg_replace('~\.+$~', '', $filename);

        if (empty($filename) && !empty($default)) {
            return $default;
        }

        return $filename;
    }

    /**
     * Create a web friendly URL slug from a string.
     *
     * Although supported, transliteration is discouraged because
     *     1) most web browsers support UTF-8 characters in URLs
     *     2) transliteration causes a loss of information
     *
     * @author    Sean Murphy <sean@iamseanmurphy.com>
     * @copyright Copyright 2012 Sean Murphy. All rights reserved.
     * @license   http://creativecommons.org/publicdomain/zero/1.0/
     *
     * @param string $str
     * @param array  $options
     *
     * @return string
     */
    public static function slug($str, $options = [])
    {
        // Make sure string is in UTF-8 and strip invalid UTF-8 characters
        if (function_exists('mb_convert_encoding')) {
            $str = mb_convert_encoding((string)$str, 'UTF-8', mb_list_encodings());
        }

        $defaults = [
            'delimiter' => '-',
            'limit' => null,
            'lowercase' => true,
            'replacements' => [],
            'transliterate' => false,
        ];

        // Merge options
        $options = array_merge($defaults, $options);

        $characterMap = [
            // Latin
            'À' => 'A',
            'Á' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'Ä' => 'A',
            'Å' => 'A',
            'Æ' => 'AE',
            'Ç' => 'C',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Ì' => 'I',
            'Í' => 'I',
            'Î' => 'I',
            'Ï' => 'I',
            'Ð' => 'D',
            'Ñ' => 'N',
            'Ò' => 'O',
            'Ó' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ö' => 'O',
            'Ő' => 'O',
            'Ø' => 'O',
            'Ù' => 'U',
            'Ú' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'Ű' => 'U',
            'Ý' => 'Y',
            'Þ' => 'TH',
            'ß' => 'ss',
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'å' => 'a',
            'æ' => 'ae',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ð' => 'd',
            'ñ' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ő' => 'o',
            'ø' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ű' => 'u',
            'ý' => 'y',
            'þ' => 'th',
            'ÿ' => 'y',

            // Latin symbols
            '©' => '(c)',

            // Greek
            'Α' => 'A',
            'Β' => 'B',
            'Γ' => 'G',
            'Δ' => 'D',
            'Ε' => 'E',
            'Ζ' => 'Z',
            'Η' => 'H',
            'Θ' => '8',
            'Ι' => 'I',
            'Κ' => 'K',
            'Λ' => 'L',
            'Μ' => 'M',
            'Ν' => 'N',
            'Ξ' => '3',
            'Ο' => 'O',
            'Π' => 'P',
            'Ρ' => 'R',
            'Σ' => 'S',
            'Τ' => 'T',
            'Υ' => 'Y',
            'Φ' => 'F',
            'Χ' => 'X',
            'Ψ' => 'PS',
            'Ω' => 'W',
            'Ά' => 'A',
            'Έ' => 'E',
            'Ί' => 'I',
            'Ό' => 'O',
            'Ύ' => 'Y',
            'Ή' => 'H',
            'Ώ' => 'W',
            'Ϊ' => 'I',
            'Ϋ' => 'Y',
            'α' => 'a',
            'β' => 'b',
            'γ' => 'g',
            'δ' => 'd',
            'ε' => 'e',
            'ζ' => 'z',
            'η' => 'h',
            'θ' => '8',
            'ι' => 'i',
            'κ' => 'k',
            'λ' => 'l',
            'μ' => 'm',
            'ν' => 'n',
            'ξ' => '3',
            'ο' => 'o',
            'π' => 'p',
            'ρ' => 'r',
            'σ' => 's',
            'τ' => 't',
            'υ' => 'y',
            'φ' => 'f',
            'χ' => 'x',
            'ψ' => 'ps',
            'ω' => 'w',
            'ά' => 'a',
            'έ' => 'e',
            'ί' => 'i',
            'ό' => 'o',
            'ύ' => 'y',
            'ή' => 'h',
            'ώ' => 'w',
            'ς' => 's',
            'ϊ' => 'i',
            'ΰ' => 'y',
            'ϋ' => 'y',
            'ΐ' => 'i',

            // Turkish
            'Ş' => 'S',
            'İ' => 'I',
            //'Ç' => 'C',
            //'Ü' => 'U',
            //'Ö' => 'O',
            'Ğ' => 'G',
            'ş' => 's',
            'ı' => 'i',
            //'ç' => 'c',
            //'ü' => 'u',
            //'ö' => 'o',
            'ğ' => 'g',

            // Russian
            'А' => 'A',
            'Б' => 'B',
            'В' => 'V',
            'Г' => 'G',
            'Д' => 'D',
            'Е' => 'E',
            'Ё' => 'Yo',
            'Ж' => 'Zh',
            'З' => 'Z',
            'И' => 'I',
            'Й' => 'J',
            'К' => 'K',
            'Л' => 'L',
            'М' => 'M',
            'Н' => 'N',
            'О' => 'O',
            'П' => 'P',
            'Р' => 'R',
            'С' => 'S',
            'Т' => 'T',
            'У' => 'U',
            'Ф' => 'F',
            'Х' => 'H',
            'Ц' => 'C',
            'Ч' => 'Ch',
            'Ш' => 'Sh',
            'Щ' => 'Sh',
            'Ъ' => '',
            'Ы' => 'Y',
            'Ь' => '',
            'Э' => 'E',
            'Ю' => 'Yu',
            'Я' => 'Ya',
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'yo',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'i',
            'й' => 'j',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'c',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'sh',
            'ъ' => '',
            'ы' => 'y',
            'ь' => '',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',

            // Ukrainian
            'Є' => 'Ye',
            'І' => 'I',
            'Ї' => 'Yi',
            'Ґ' => 'G',
            'є' => 'ye',
            'і' => 'i',
            'ї' => 'yi',
            'ґ' => 'g',

            // Czech
            'Č' => 'C',
            'Ď' => 'D',
            'Ě' => 'E',
            'Ň' => 'N',
            'Ř' => 'R',
            'Š' => 'S',
            'Ť' => 'T',
            'Ů' => 'U',
            'Ž' => 'Z',
            'č' => 'c',
            'ď' => 'd',
            'ě' => 'e',
            'ň' => 'n',
            'ř' => 'r',
            'š' => 's',
            'ť' => 't',
            'ů' => 'u',
            'ž' => 'z',

            // Polish
            'Ą' => 'A',
            'Ć' => 'C',
            'Ę' => 'e',
            'Ł' => 'L',
            'Ń' => 'N',
            //'Ó' => 'O',
            'Ś' => 'S',
            'Ź' => 'Z',
            'Ż' => 'Z',
            'ą' => 'a',
            'ć' => 'c',
            'ę' => 'e',
            'ł' => 'l',
            'ń' => 'n',
            //'ó' => 'o',
            'ś' => 's',
            'ź' => 'z',
            'ż' => 'z',

            // Latvian
            'Ā' => 'A',
            //'Č' => 'C',
            'Ē' => 'E',
            'Ģ' => 'G',
            'Ī' => 'i',
            'Ķ' => 'k',
            'Ļ' => 'L',
            'Ņ' => 'N',
            //'Š' => 'S',
            'Ū' => 'u',
            //'Ž' => 'Z',
            'ā' => 'a',
            //'č' => 'c',
            'ē' => 'e',
            'ģ' => 'g',
            'ī' => 'i',
            'ķ' => 'k',
            'ļ' => 'l',
            'ņ' => 'n',
            //'š' => 's',
            'ū' => 'u',
            //'ž' => 'z'
        ];

        // Make custom replacements
        if (!empty($options['replacements'])) {
            $str = preg_replace(array_keys($options['replacements']), $options['replacements'], $str);
        }

        // Transliterate characters to ASCII
        if ($options['transliterate']) {
            $str = strtr($str, $characterMap);
        }

        // Replace non-alphanumeric characters with our delimiter
        $str = preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $str);

        // Remove duplicate delimiters
        $str = preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $str);

        // Truncate slug to max. characters
        $str = mb_substr($str, 0, ($options['limit'] ? $options['limit'] : mb_strlen($str, 'UTF-8')), 'UTF-8');

        // Remove delimiter from ends
        $str = trim($str, $options['delimiter']);

        return $options['lowercase'] ? mb_strtolower($str, 'UTF-8') : $str;
    }

    /**
     * Returns whether passed string is ending with passed phrase
     *
     * @param string $needle
     * @param string $haystack
     *
     * @return bool
     */
    public static function endsWith($needle, $haystack)
    {
        $len = strlen($needle);

        return ($len > strlen($haystack)) ? false :
            (substr($haystack, -$len) === $needle);
    }

    /**
     * Returns the input CamelCasedString as an underscored_string.
     *
     * @param string $string Camel cased string to be underscored
     *
     * @return string
     */
    public static function underscore($string)
    {
        return mb_strtolower(preg_replace(
            '/(?<=\\w)([A-Z])/',
            '_' . '\\1',
            str_replace('-', '_', $string)
        ));
    }

    /**
     * Returns the input lower_case_delimited_string as a CamelCasedString.
     *
     * @param string       $string    String to camelize
     * @param string|array $delimiter the delimiter in the input string
     *
     * @return string CamelizedStringLikeThis.
     */
    public static function camelize($string, $delimiter)
    {
        $result = explode(' ', str_replace($delimiter, ' ', $string));
        foreach ($result as &$word) {
            $word = mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1);
        }

        return implode('', $result);
    }
}
