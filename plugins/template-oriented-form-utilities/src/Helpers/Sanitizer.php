<?php

namespace TofuPlugin\Helpers;

class Sanitizer
{
    /**
     * Recursive esc_html sanitization of an array of strings.
     *
     * @param mixed $input The input array to sanitize.
     * @return mixed The sanitized array.
     */
    public static function escHtmlRecursive($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'escHtmlRecursive'], $input);
        }

        if (!is_string($input)) {
            return $input;
        }

        return esc_html($input);
    }

    /**
     * Recursive esc_attr sanitization of an array of strings.
     *
     * @param mixed $input The input array to sanitize.
     * @return mixed The sanitized array.
     */
    public static function escAttrRecursive($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'escAttrRecursive'], $input);
        }

        if (!is_string($input)) {
            return $input;
        }

        return esc_html($input);
    }
}
