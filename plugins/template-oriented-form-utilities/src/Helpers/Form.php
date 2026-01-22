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
                sprintf('Form with key "%s" is already registered.', esc_html($config->key)),
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
    public static function get(string $key, bool $isStrict = true): FormModel|false
    {
        foreach (self::$forms as $form) {
            if ($form->getKey() === $key) {
                return $form;
            }
        }

        if ($isStrict) {
            wp_die(
                sprintf('Form with key "%s" is not registered.', esc_html($key)),
                'TOFU Form Action Error',
                ['response' => 500]
            );
        }
        return false;
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
        $attributes['id'] = sprintf(Consts::FORM_ID_FORMAT, $key);

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
     * @param bool $raw
     * @return mixed
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

        return Sanitizer::escHtmlRecursive($value->value);
    }

    /**
     * Check if the submitted values contain the specified value for checkbox/radio/select
     *
     * @param string $key
     * @param string $field
     * @param string $value
     * @return bool
     */
    public static function contains(string $key, string $field, string $value): bool
    {
        $form = self::get($key);
        $fieldValue = $form->getValues()->getValue($field);
        if ($fieldValue === null) {
            return false;
        }

        if (is_array($fieldValue->value)) {
            return in_array($value, $fieldValue->value, true);
        }

        return ($fieldValue->value === $value);
    }

    /**
     * Return `checked` attribute if the checkbox/radio is checked
     *
     * @param string $key
     * @param string $field
     * @param string $value
     * @return string
     */
    public static function checked(string $key, string $field, string $value): string
    {
        return self::contains($key, $field, $value) ? 'checked' : '';
    }

    /**
     * Return `selected` attribute if the select option is selected
     *
     * @param string $key
     * @param string $field
     * @param string $value
     * @return string
     */
    public static function selected(string $key, string $field, string $value): string
    {
        return self::contains($key, $field, $value) ? 'selected' : '';
    }

    /**
     * Check if the uploaded file exists for the specified field
     *
     * @param string $key
     * @param string $field
     * @return bool
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
        $file = $form->getFiles()->getFile($field);
        return $file;
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

        // Embed hidden input field for the uploaded file
        // The input name is like: __tofu_uploaded_files[field_name]
        // The input value is the unique ID of the uploaded file that can be used to retrieve the file info from the session
        return sprintf(
            '<input type="hidden" name="%s[%s]" value="%s" %s />',
            Consts::UPLOADED_FILES_INPUT_NAME,
            esc_attr($file->name),
            esc_attr($file->getId()),
            self::getFileDataAttribute($key, $file->name),
        );
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
     * @return bool
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
     * Embed the reCAPTCHA/Turnstile script for the given form.
     *
     * This method enqueues the Google reCAPTCHA/Cloudflare Turnstile script and
     * the plugin's own JavaScript that handles token generation.
     * It must be called before {@see get_header()} (i.e. before WordPress
     * outputs the <head> section) so that the scripts are properly enqueued.
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
        if ($recaptchaConfig !== null) {
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
                    'formId' => sprintf(Consts::FORM_ID_FORMAT, $key),
                    'inputId' => sprintf(Consts::RECAPTCHA_TOKEN_INPUT_ID_FORMAT, $key),
                ]
            );
        }

        // Check if Turnstile is configured for the form
        $turnstileConfig = $form->getTurnstileConfig();
        if ($turnstileConfig !== null) {
            wp_enqueue_script(
                'tofu-cloudflare-turnstile',
                'https://challenges.cloudflare.com/turnstile/v0/api.js',
                [],
                null,
                false
            );
        }
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
     * Has Turnstile configured
     *
     * @return bool
     */
    public static function hasTurnstile(string $key): bool
    {
        $form = self::get($key);
        return $form->hasTurnstile();
    }

    /**
     * Get hidden input field for Turnstile token
     *
     * @param string $key
     * @return string
     */
    public static function turnstileWidget(string $key, array $attributes = []): string
    {
        $form = self::get($key);
        $turnstileConfig = $form->getTurnstileConfig();
        if ($turnstileConfig === null) {
            return '';
        }

        $attributes = shortcode_atts(
            [
                'data-action' => null,
                'data-cdata' => null,
                'data-callback' => null,
                'data-error-callback' => null,
                'data-execution' => null,
                'data-expired-callback' => null,
                'data-before-interactive-callback' => null,
                'data-after-interactive-callback' => null,
                'data-unsupported-callback' => null,
                'data-theme' => null,
                'data-language' => null,
                'data-tabindex' => null,
                'data-timeout-callback' => null,
                'data-response-field' => null,
                'data-size' => null,
                'data-retry' => null,
                'data-retry-interval' => null,
                'data-refresh-expired' => null,
                'data-refresh-timeout' => null,
                'data-appearance' => null,
                'data-feedback-enabled' => null,
            ],
            $attributes,
        );

        $display = [];
        foreach ($attributes as $k => $a) {
            if (empty($a)) {
                continue;
            }

            $display[] = esc_attr($k) . '=' . esc_attr($a);
        }
        $display[] = 'data-response-field-name="' . esc_attr(Consts::TURNSTILE_TOKEN_INPUT_NAME) . '"';

        return sprintf(
            '<div class="cf-turnstile" data-sitekey="%s" %s></div>',
            esc_attr($turnstileConfig->siteKey),
            implode(' ', $display)
        );
    }

    /**
     * Check if the form has error for Turnstile
     *
     * @param string $key
     * @return bool
     */
    public static function hasTurnstileError(string $key): bool
    {
        return self::hasError($key, Consts::TURNSTILE_TOKEN_INPUT_NAME);
    }

    /**
     * Get form error messages of Turnstile
     *
     * @param string $key
     * @return string[]
     */
    public static function turnstileErrors(string $key): array
    {
        return self::errors($key, Consts::TURNSTILE_TOKEN_INPUT_NAME);
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
        $form = self::get($key);
        $form->redirect($action);
    }
}
