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
         * Template setting.
         *
         * @var TemplateConfig
         */
        public readonly TemplateConfig $template,

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
    )
    {
    }
}
