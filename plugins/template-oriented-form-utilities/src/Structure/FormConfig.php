<?php

namespace TofuPlugin\Structure;

/**
 * Form configuration class.
 *
 * This class is used to define the configuration for a form.
 *
 * @package TofuPlugin\Structure
 */
class FormConfig
{
    public function __construct(
        /**
         * Key for the form item.
         *
         * @var string
         */
        public readonly string $key,

        /**
         * Form name.
         *
         * @var string
         */
        public readonly string $name,

        /**
         * Mail setting.
         *
         * @var MailConfig
         */
        public readonly MailConfig $mail,

        /**
         * Validation setting.
         *
         * @var ValidationConfig
         */
        public readonly ValidationConfig $validation,

        /**
         * Template setting.
         *
         * Required for the traditional redirect-based form flow.
         * Can be omitted when using AJAX / headless mode only.
         *
         * @var ?TemplateConfig
         */
        public readonly ?TemplateConfig $template = null,

        /**
         * Enabled to save the form data to the database after emails are sent.
         *
         * When true, validated field values are encrypted with AES-256-CBC via
         * `Encryptor::encrypt()` and persisted in `wp_tofu_records` immediately
         * after all emails are dispatched successfully. A save failure is
         * non-fatal: it is logged and the submission still completes normally.
         *
         * Use `validation->records` to restrict which fields are persisted.
         *
         * @var bool
         */
        public readonly bool $saveToDatabase = false,

        /**
         * Enable reCAPTCHA bot protection for this form.
         *
         * Requires `Form::setRecaptcha()` to be called with a plugin-level
         * `ReCAPTCHAConfig` before this form is rendered.
         *
         * @var bool
         */
        public readonly bool $recaptchaEnabled = false,

        /**
         * Enable Cloudflare Turnstile bot protection for this form.
         *
         * Requires `Form::setTurnstile()` to be called with a plugin-level
         * `TurnstileConfig` before this form is rendered.
         *
         * @var bool
         */
        public readonly bool $turnstileEnabled = false,

        /**
         * Enable the WP REST API (AJAX / headless) endpoint for this form.
         *
         * When true, the following routes are registered:
         *   GET  /wp-json/tofu/v1/forms/{key}/nonce
         *   POST /wp-json/tofu/v1/forms/{key}/input
         *   POST /wp-json/tofu/v1/forms/{key}/confirm
         *
         * @var bool
         */
        public readonly bool $ajaxEnabled = false,

        /**
         * Allowed CORS origins for the REST endpoint.
         *
         * Leave empty (default) to allow same-origin requests only.
         * Set one or more origins to enable cross-domain AJAX, e.g.:
         *   ['https://frontend.example.com']
         *
         * When non-empty, the plugin automatically sets
         * `SameSite=None; Secure` on the session cookie so that
         * cross-origin requests with `credentials: 'include'` work.
         * HTTPS is required for this to function.
         *
         * @var string[]
         */
        public readonly array $corsOrigins = [],

        /**
         * Whether this form has a confirm step.
         *
         * Must be set to `true` to enable the confirm step — for both the traditional
         * redirect flow (also set `template->confirmPath`) and AJAX / headless mode.
         *
         * @var bool
         */
        public readonly bool $confirmStep = false,
    )
    {
        if ($this->confirmStep && !$this->ajaxEnabled && empty($this->template?->confirmPath)) {
            throw new \InvalidArgumentException(
                "FormConfig '{$this->key}': confirmStep is true but template->confirmPath is not set. " .
                'For the redirect flow set template->confirmPath; for AJAX / headless set ajaxEnabled: true.'
            );
        }
    }

    /**
     * Returns true when this form has a confirmation step.
     *
     * This is determined solely by the `confirmStep` flag.
     * For the traditional redirect flow, set both `confirmStep: true` and
     * `template->confirmPath`. For AJAX / headless, set only `confirmStep: true`.
     *
     * @return bool
     */
    public function hasConfirmStep(): bool
    {
        return $this->confirmStep;
    }
}
