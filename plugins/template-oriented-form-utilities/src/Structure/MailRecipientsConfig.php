<?php

namespace TofuPlugin\Structure;

use TofuPlugin\Helpers\Validate;

/**
 * Mail Template configuration class.
 *
 * @package TofuPlugin\Structure
 */
class MailRecipientsConfig
{
    public function __construct(
        /**
         * Recipient email address.
         * If you want to skip the recipient email, set this to null.
         *
         * If you set `{field}`, it will be replaced with the value of the field.
         * For example, if you set `{email}`, it will be replaced with the value of the `email` field.
         *
         * @var string|null
         */
        public readonly string | null $recipientEmail = null,

        /**
         * Recipient cc email address.
         * If you want to skip the recipient cc email, set this to null.
         *
         * If you set `{field}`, it will be replaced with the value of the field.
         * For example, if you set `{cc}`, it will be replaced with the value of the `cc` field.
         *
         * @var string|null
         */
        public readonly string | null $recipientCcEmail = null,

        /**
         * Recipient bcc email address.
         * If you want to skip the recipient bcc email, set this to null.
         *
         * If you set `{field}`, it will be replaced with the value of the field.
         * For example, if you set `{bcc}`, it will be replaced with the value of the `bcc` field.
         *
         * @var string|null
         */
        public readonly string | null $recipientBccEmail = null,

        /**
         * Recipient email subject template.
         * If you want to skip the recipient email, set this to null.
         *
         * @var string|null
         */
        public readonly string | null $subjectPath = null,

        /**
         * Recipient email body template.
         * If you want to skip the recipient email, set this to null.
         *
         * @var string|null
         */
        public readonly string | null $mailBodyPath = null,
    ) {
        // Validate email address.
        // If you set `{field}`, it will be replaced with the value of the field, so ensure it's a valid email format.
        if (!preg_match('/^\{[a-zA-Z0-9_]+\}$/', $recipientEmail)) {
            if (!Validate::isValidEmail($recipientEmail)) {
                throw new \InvalidArgumentException('Invalid recipient email address.');
            }
        }
        if (!preg_match('/^\{[a-zA-Z0-9_]+\}$/', $recipientCcEmail)) {
            if (!Validate::isValidEmail($recipientCcEmail)) {
                throw new \InvalidArgumentException('Invalid recipient cc email address.');
            }
        }
        if (!preg_match('/^\{[a-zA-Z0-9_]+\}$/', $recipientBccEmail)) {
            if (!Validate::isValidEmail($recipientBccEmail)) {
                throw new \InvalidArgumentException('Invalid recipient bcc email address.');
            }
        }
    }
}
