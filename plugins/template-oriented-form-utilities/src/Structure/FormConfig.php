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
         * Enabled to save the form data to the database.
         * If you want to skip saving the form data, set this to false.
         *
         * @var bool
         * @todo Implement the save to database functionality.
         */
        public readonly bool $saveToDatabase = false,

        /**
         * reCAPTCHA setting.
         *
         * @var ?ReCAPTCHAConfig
         */
        public readonly ?ReCAPTCHAConfig $recaptcha = null,

        /**
         * Turnstile setting.
         *
         * @var ?TurnstileConfig
         */
        public readonly ?TurnstileConfig $turnstile = null,

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
         * In the traditional redirect flow this is inferred from whether
         * `template->confirmPath` is set. In AJAX / headless mode (where
         * `template` may be null) set this to `true` explicitly to enable
         * the confirm step.
         *
         * @var bool
         */
        public readonly bool $confirmStep = false,
    )
    {
        if ($this->confirmStep && !$this->ajaxEnabled && empty($this->template?->confirmPath)) {
            throw new \InvalidArgumentException(
                "FormConfig '{$this->key}': confirmStep is true but template->confirmPath is not set. " .
                'Set template->confirmPath for the redirect flow, or set ajaxEnabled: true for AJAX / headless mode.'
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
