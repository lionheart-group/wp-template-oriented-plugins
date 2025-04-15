<?php

namespace TofuPlugin\Structure;

use TofuPlugin\Helpers\Validate;

/**
 * Mail configuration class.
 *
 * @package TofuPlugin\Structure
 */
class MailConfig
{
    public function __construct(
        /**
         * From email address.
         * If you set null, the default email address will be used.
         * This is usually the same as the site URL.
         *
         * @var string|null
         */
        public readonly string | null $fromEmail = null,

        /**
         * From name.
         * If you set null, from name won't be set.
         *
         * @var string|null
         */
        public readonly string | null $fromName = null,

        /**
         * Email recipient collection.
         *
         * @var MailRecipientsCollection
         */
        public readonly MailRecipientsCollection $recipients,
    ) {
        // Validate the email addresses.
        if (!Validate::isValidEmail($fromEmail)) {
            throw new \InvalidArgumentException('Invalid from email address.');
        }
    }
}
