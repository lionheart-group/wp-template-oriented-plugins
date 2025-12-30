<?php

namespace TofuPlugin\Helpers;

use TofuPlugin\Consts;
use \TofuPlugin\Models\Form as FormModel;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\UploadedFile;

class Form
{
    /**
     * Form list
     *
     * @var FormModel[]
     */
    protected static $forms = [];

    /**
     * Register a new form
     *
     * @param FormConfig $config
     * @return void
     */
    public static function register(FormConfig $config)
    {
        // Check if the form is already registered
        $form = self::get($config->key, false);
        if ($form) {
            wp_die(
                sprintf('Form with key "%s" is already registered.', $config->key),
                'TOFU Form Registration Error',
                ['response' => 500]
            );

            return;
        }

        self::$forms[] = new \TofuPlugin\Models\Form($config);
    }

    /**
     * Get form by key
     *
     * @return FormModel|false
     */
    public static function get(string $key, bool $isStrict = true)
    {
        foreach (self::$forms as $form) {
            if ($form->getKey() === $key) {
                return $form;
            }
        }

        if ($isStrict) {
            wp_die(
                sprintf('Form with key "%s" is not registered.', $key),
                'TOFU Form Action Error',
                ['response' => 500]
            );
            exit;
        } else {
            return false;
        }
    }

    /**
     * Get action URL for the form
     *
     * @param string $key
     * @return string
     */
    public static function action(string $key, string $action)
    {
        $form = self::get($key);
        return $form->getActionUrl($action);
    }

    /**
     * Generate form tag
     *
     * @param string $key
     * @param string $action
     * @return string
     */
    public static function formOpen(string $key, string $action, array $attributes = []): string
    {
        $form = self::get($key);
        $actionUrl = $form->getActionUrl($action);

        $attrString = '';
        foreach ($attributes as $attrKey => $attrValue) {
            $attrString .= sprintf(' %s="%s"', $attrKey, esc_attr($attrValue));
        }

        return sprintf('<form action="%s" method="post" enctype="multipart/form-data"%s>', esc_url($actionUrl), $attrString);
    }

    /**
     * Generate form closing tag
     *
     * @return string
     */
    public static function formClose(): string
    {
        return '</form>';
    }

    /**
     * Get form value
     *
     * @param string $key
     * @param string $field
     */
    public static function value(string $key, string $field, bool $raw = false): mixed
    {
        $form = self::get($key);
        $value = $form->getValues()->getValue($field);

        if ($value === null) {
            return null;
        }

        if ($raw) {
            return $value->value;
        }

        if (is_string($value->value)) {
            return esc_html($value->value);
        }
        return $value->value;
    }

    /**
     * Check if the uploaded file exists for the specified field
     *
     * @param string $key
     * @param string $field
     * @return boolean
     */
    public static function hasFile(string $key, string $field): bool
    {
        $form = self::get($key);
        return $form->getFiles()->hasFile($field);
    }

    /**
     * Get uploaded file for the specified field
     *
     * @param string $key
     * @param string $field
     * @return ?UploadedFile
     */
    public static function file(string $key, string $field): ?UploadedFile
    {
        $form = self::get($key);
        return $form->getFiles()->getFile($field);
    }

    /**
     * Generate hidden input field for the uploaded file
     *
     * @param string $key
     * @param string $field
     * @return string
     */
    public static function fileHidden(string $key, string $field): string
    {
        $form = self::get($key);
        $file = $form->getFiles()->getFile($field);

        if ($file === null) {
            return '';
        }

        $outputs = [];

        $outputs[] = sprintf(
            '<input type="hidden" name="%s[%s][name]" value="%s">',
            Consts::UPLOADED_FILES_INPUT_NAME,
            esc_attr($file->name),
            esc_attr($file->name)
        );
        $outputs[] = sprintf(
            '<input type="hidden" name="%s[%s][fileName]" value="%s">',
            Consts::UPLOADED_FILES_INPUT_NAME,
            esc_attr($file->name),
            esc_attr($file->fileName)
        );
        $outputs[] = sprintf(
            '<input type="hidden" name="%s[%s][mimeType]" value="%s">',
            Consts::UPLOADED_FILES_INPUT_NAME,
            esc_attr($file->name),
            esc_attr($file->mimeType)
        );
        $outputs[] = sprintf(
            '<input type="hidden" name="%s[%s][tempName]" value="%s">',
            Consts::UPLOADED_FILES_INPUT_NAME,
            esc_attr($file->name),
            esc_attr($file->tempName)
        );
        $outputs[] = sprintf(
            '<input type="hidden" name="%s[%s][size]" value="%d">',
            Consts::UPLOADED_FILES_INPUT_NAME,
            esc_attr($file->name),
            $file->size
        );

        return implode("", $outputs);
    }

    /**
     * Check if the form has error for the specified field
     *
     * @param string $key
     * @param string $field
     * @return boolean
     */
    public static function hasError(string $key, string $field): bool
    {
        $form = self::get($key);
        return $form->getErrors()->hasFieldErrors($field);
    }

    /**
     * Get form error messages of the specified field
     *
     * @param string $key
     * @param string $field
     * @return string[]
     */
    public static function errors(string $key, string $field): array
    {
        $form = self::get($key);
        return $form->getErrors()->getFieldErrorMessages($field);
    }

    /**
     * Generate nonce field
     *
     * @return string
     */
    public static function generateNonceField(string $key, string $action): void
    {
        $nonceKey = sprintf(Consts::NONCE_FORMAT, $key);
        wp_nonce_field($action, $nonceKey, false, true);
    }

    /**
     * Verify nonce field
     *
     * @return bool
     */
    public static function verifyNonceField(string $key, string $action): bool
    {
        $nonceKey = sprintf(Consts::NONCE_FORMAT, $key);
        $nonce = $_POST[$nonceKey] ?? null;

        if (!isset($nonce)) {
            return false;
        }

        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Verify session value
     *
     * @return bool
     */
    public static function verifySession(string $key): bool
    {
        $form = self::get($key);
        return $form->verifySession();
    }

    /**
     * Verify submit step
     *
     * @return bool
     */
    public static function verifySubmit(string $key): bool
    {
        $form = self::get($key);
        return $form->verifySubmit();
    }
}
