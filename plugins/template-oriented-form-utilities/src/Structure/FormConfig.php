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
         * Enabeld to save the form data to the database.
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
    )
    {
    }
}
