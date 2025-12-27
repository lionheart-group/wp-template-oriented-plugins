<?php

namespace TofuPlugin\Helpers;

use TofuPlugin\Logger;

class Template
{
    /**
     * Undocumented function
     *
     * @param string      $slug The slug name for the generic template.
     * @param string|null $name Optional. The name of the specialized template. Default null.
     * @param array       $args Optional. Additional arguments passed to the template.
     *                          Default empty array.
     * @return string
     */
    public static function getTemplateContent(string $slug, string|null $name = null, array $args = array()): string
    {
        ob_start();
        get_template_part($slug, $name, $args);
        $result = ob_get_contents();
        ob_end_clean();
        Logger::info("Loaded template content", ["slug" => $slug, "name" => $name, "args" => $args, 'content' => $result]);
        return $result;
    }

    /**
     * Replace brace placeholders in the template content.
     *
     * @param string $templateContent The template content with placeholders.
     * @param array  $placeholders    An associative array of placeholders and their replacements.
     * @return string The template content with placeholders replaced.
     */
    public static function replaceBlacesValues(string $templateContent, array $values)
    {
        foreach ($values as $key => $value) {
            $templateContent = preg_replace('/\{\s*?' . preg_quote($key, '/') . '\s*?\}/', $value, $templateContent);
        }
        return $templateContent;
    }
}
