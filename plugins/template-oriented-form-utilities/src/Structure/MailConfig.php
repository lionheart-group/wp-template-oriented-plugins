<?php

namespace TofuPlugin\Structure;

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
        public readonly string $fromEmail,

        /**
         * From name.
         * If you set null, from name won't be set.
         *
         * @var string|null
         */
        public readonly string $fromName,

        /**
         * Email recipient collection.
         *
         * @var MailRecipientsCollection
         */
        public readonly MailRecipientsCollection $recipients,
    ) {
    }
}
