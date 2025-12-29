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
         * This is usually the same as the site URL.
         *
         * @var string
         */
        public readonly string $fromEmail,

        /**
         * From name.
         *
         * @var string
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
