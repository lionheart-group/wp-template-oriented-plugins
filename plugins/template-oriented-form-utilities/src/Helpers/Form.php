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
        } else {
            return false;
        }
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

        // Forcibly set id attribute
        $attributes['id'] = sprintf(Consts::RECAPTCHA_TOKEN_FORM_ID_FORMAT, $key);

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
    public static function formClose(string $key, string $action): string
    {
        return self::hidden($key, $action) . '</form>';
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
     * Get data attribute name for the uploaded file
     *
     * @param string $key
     * @param string $field
     * @return string
     */
    public static function getFileDataAttribute(string $key, string $field): string
    {
        return sprintf('data-tofu-field="%s.%s"', esc_attr($key), esc_attr($field));
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
            '<input type="hidden" name="%s[%s][name]" value="%s" %s />',
            Consts::UPLOADED_FILES_INPUT_NAME,
            esc_attr($file->name),
            esc_attr($file->name),
            self::getFileDataAttribute($key, $file->name),
        );
        $outputs[] = sprintf(
            '<input type="hidden" name="%s[%s][fileName]" value="%s" %s />',
            Consts::UPLOADED_FILES_INPUT_NAME,
            esc_attr($file->name),
            esc_attr($file->fileName),
            self::getFileDataAttribute($key, $file->name),
        );
        $outputs[] = sprintf(
            '<input type="hidden" name="%s[%s][mimeType]" value="%s" %s />',
            Consts::UPLOADED_FILES_INPUT_NAME,
            esc_attr($file->name),
            esc_attr($file->mimeType),
            self::getFileDataAttribute($key, $file->name),
        );
        $outputs[] = sprintf(
            '<input type="hidden" name="%s[%s][tempName]" value="%s" %s />',
            Consts::UPLOADED_FILES_INPUT_NAME,
            esc_attr($file->name),
            esc_attr($file->tempName),
            self::getFileDataAttribute($key, $file->name),
        );
        $outputs[] = sprintf(
            '<input type="hidden" name="%s[%s][size]" value="%d" %s />',
            Consts::UPLOADED_FILES_INPUT_NAME,
            esc_attr($file->name),
            $file->size,
            self::getFileDataAttribute($key, $file->name),
        );

        return implode("", $outputs);
    }

    /**
     * Generate file remove button
     *
     * @param string $key
     * @param string $field
     * @return string
     */
    public static function fileRemoveButton(string $key, string $field, ?string $label = null, array $attributes = []): string
    {
        $form = self::get($key);
        $file = $form->getFiles()->getFile($field);

        if ($file === null) {
            return '';
        }

        $attrString = '';
        foreach ($attributes as $attrKey => $attrValue) {
            $attrString .= sprintf(' %s="%s"', $attrKey, esc_attr($attrValue));
        }

        return sprintf(
            '<button type="button" data-tofu-target="%s.%s"%s>%s</button>',
            esc_attr($key),
            esc_attr($field),
            $attrString,
            $label !== null ? esc_html($label) : esc_html__('Remove File', 'template-oriented-form-utilities')
        );
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
     * Has reCAPTCHA configured
     *
     * @return bool
     */
    public static function hasRecaptcha(string $key): bool
    {
        $form = self::get($key);
        return $form->hasRecaptcha();
    }

    /**
     * Embed the reCAPTCHA script for the given form.
     *
     * This method enqueues the Google reCAPTCHA script and the plugin's
     * own JavaScript that handles token generation. It must be called
     * before {@see get_header()} (i.e. before WordPress outputs the
     * <head> section) so that the scripts are properly enqueued.
     *
     * Typical usage in a theme template:
     *
     * <code>
     * <?php
     * use TofuPlugin\Helpers\Form;
     *
     * // Ensure scripts are enqueued before get_header().
     * Form::embedScript('contact');
     *
     * get_header();
     * ?>
     * </code>
     *
     * @param string $key Form key used when registering the form.
     * @return void
     */
    public static function embedScript(string $key): void
    {
        $form = self::get($key);

        // Enqueue common script
        wp_enqueue_script(
            'tofu-file-input',
            plugins_url('/assets/js/file-input.js', TOFU_PLUGIN_FILE),
            [],
            filemtime(plugin_dir_path(TOFU_PLUGIN_FILE) . 'assets/js/file-input.js'),
            false
        );

        // Check if reCAPTCHA is configured for the form
        $recaptchaConfig = $form->getRecaptchaConfig();
        if ($recaptchaConfig === null) {
            return;
        }

        wp_enqueue_script(
            'tofu-google-recaptcha',
            sprintf('https://www.google.com/recaptcha/api.js?render=%s', rawurlencode($recaptchaConfig->siteKey)),
            [],
            null,
            false
        );
        wp_enqueue_script(
            'tofu-user-recaptcha',
            plugins_url('/assets/js/recaptcha.js', TOFU_PLUGIN_FILE),
            ['tofu-google-recaptcha'],
            filemtime(plugin_dir_path(TOFU_PLUGIN_FILE) . 'assets/js/recaptcha.js'),
            false
        );
        wp_localize_script(
            'tofu-user-recaptcha',
            'tofuRecaptchaConfig',
            [
                'siteKey' => $recaptchaConfig->siteKey,
                'formId' => sprintf(Consts::RECAPTCHA_TOKEN_FORM_ID_FORMAT, $key),
                'inputId' => sprintf(Consts::RECAPTCHA_TOKEN_INPUT_ID_FORMAT, $key),
            ]
        );
    }

    /**
     * Get hidden input field for reCAPTCHA token
     *
     * @param string $key
     * @return string
     */
    public static function recaptchaHidden(string $key): string
    {
        $form = self::get($key);
        $recaptchaConfig = $form->getRecaptchaConfig();
        if ($recaptchaConfig === null) {
            return '';
        }

        return sprintf(
            '<input type="hidden" name="%s" id="%s">',
            Consts::RECAPTCHA_TOKEN_INPUT_NAME,
            esc_attr(sprintf(Consts::RECAPTCHA_TOKEN_INPUT_ID_FORMAT, $key))
        );
    }

    /**
     * Check if the form has error for reCAPTCHA
     *
     * @param string $key
     * @return bool
     */
    public static function hasRecaptchaError(string $key): bool
    {
        return self::hasError($key, Consts::RECAPTCHA_TOKEN_INPUT_NAME);
    }

    /**
     * Get form error messages of reCAPTCHA
     *
     * @param string $key
     * @return string[]
     */
    public static function recaptchaErrors(string $key): array
    {
        return self::errors($key, Consts::RECAPTCHA_TOKEN_INPUT_NAME);
    }

    /**
     * Generate nonce field
     *
     * @return string
     */
    public static function generateNonceField(string $key, string $action): string
    {
        $nonceKey = sprintf(Consts::NONCE_FORMAT, $key);
        return wp_nonce_field($action, $nonceKey, false, false);
    }

    /**
     * Embed hidden fields for session and nonce verification
     *
     * @return string
     */
    public static function hidden(string $key, string $action): string
    {
        return self::recaptchaHidden($key) . self::generateNonceField($key, $action);
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

    /**
     * Redirect to the target page.
     *
     * @param string $action
     * @return void
     */
    public static function redirect(string $key, string $action): void
    {
        // Check if the action is valid
        if (!in_array($action, ['input', 'confirm'])) {
            wp_die('Invalid action.', 'TOFU Form Action Error', ['response' => 400]);
        }

        $form = self::get($key);

        switch ($action) {
            case 'input':
                $redirectUrl = $form->config->template->inputPath;
                break;
            case 'confirm':
                $redirectUrl = $form->config->template->confirmPath;
                break;
        }

        if ($redirectUrl === null) {
            wp_die('Redirect URL is not configured.', 'TOFU Form Action Error', ['response' => 500]);
        }

        wp_redirect($redirectUrl);
        exit;
    }
}
