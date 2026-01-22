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
    public static function escHtmlRecursive($input): mixed
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
    public static function escAttrRecursive($input): mixed
    {
        if (is_array($input)) {
            return array_map([self::class, 'escAttrRecursive'], $input);
        }

        if (!is_string($input)) {
            return $input;
        }

        return esc_attr($input);
    }

    /**
     * Recursive sanitize_text_field sanitization of an array of strings.
     *
     * @param mixed $input The input array to sanitize.
     * @return mixed The sanitized array.
     */
    public static function sanitizeTextFieldRecursive($input): mixed
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeTextFieldRecursive'], $input);
        }

        if (!is_string($input)) {
            return $input;
        }

        return sanitize_text_field($input);
    }

    /**
     * Sanitize input for safe logging.
     *
     * @param mixed $input The input to sanitize.
     * @return string The sanitized string.
     */
    public static function getSanitizedLoggerString($input): string
    {
        $input = json_encode(
            $input,
            JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES
        );

        if (json_last_error() !== JSON_ERROR_NONE || $input === false) {
            return '[[unserializable data]]';
        }

        return sanitize_text_field($input);
    }
}
