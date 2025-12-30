<?php

namespace TofuPlugin\Helpers;

use TofuPlugin\Logger;

class Template
{
    /**
     * Get the template content as a string by rendering a template part.
     *
     * @param string  $slug The slug name for the generic template.
     * @param ?string $name Optional. The name of the specialized template. Default null.
     * @param array   $args Optional. Additional arguments passed to the template.
     *                      Default empty array.
     * @return string
     */
    public static function getTemplateContent(string $slug, ?string $name = null, array $args = array()): string
    {
        ob_start();
        get_template_part($slug, $name, $args);
        $result = (string) ob_get_clean();
        Logger::info("Loaded template content", ["slug" => $slug, "name" => $name, "args" => $args, 'content' => $result]);
        return $result;
    }

    /**
     * Replace brace placeholders in the template content.
     *
     * @param string $templateContent The template content with placeholders.
     * @param array  $values          An associative array of placeholders and their replacements.
     * @return string The template content with placeholders replaced.
     */
    public static function replaceBracesValues(string $templateContent, array $values): string
    {
        foreach ($values as $key => $value) {
            $replaceValue = '';
            if (is_array($value) || is_object($value)) {
                $replaceValue = print_r($value, true);
            } else {
                $replaceValue = (string)$value;
            }

            $templateContent = preg_replace('/\{\s*?' . preg_quote($key, '/') . '\s*?\}/', $replaceValue, $templateContent);
        }
        return $templateContent;
    }
}
