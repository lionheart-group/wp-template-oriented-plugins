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

        /**
         * Return-Path (envelope sender) address.
         * Bounces are delivered here. When null, the server's default is used.
         *
         * @var string|null
         */
        public readonly ?string $returnPath = null,
    ) {
        if ($this->returnPath !== null && !is_email($this->returnPath)) {
            throw new \InvalidArgumentException('Invalid Return-Path email address.');
        }
    }
}
