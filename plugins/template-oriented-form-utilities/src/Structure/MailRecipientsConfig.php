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
         *
         * If you set `{field}`, it will be replaced with the value of the field.
         * For example, if you set `{email}`, it will be replaced with the value of the `email` field.
         *
         * @var string
         */
        public readonly string $recipientEmail,

        /**
         * Recipient cc email address.
         * If you want to skip the recipient cc email, set this to null.
         *
         * If you set `{field}`, it will be replaced with the value of the field.
         * For example, if you set `{cc}`, it will be replaced with the value of the `cc` field.
         *
         * @var ?string
         */
        public readonly ?string $recipientCcEmail = null,

        /**
         * Recipient bcc email address.
         * If you want to skip the recipient bcc email, set this to null.
         *
         * If you set `{field}`, it will be replaced with the value of the field.
         * For example, if you set `{bcc}`, it will be replaced with the value of the `bcc` field.
         *
         * @var ?string
         */
        public readonly ?string $recipientBccEmail = null,

        /**
         * Recipient email subject.
         * You can choose to set a static subject here or use a template path.
         *
         * @var ?string
         */
        public readonly ?string $subject = null,

        /**
         * Recipient email subject template.
         * You can choose to set a template path here or use a static subject.
         *
         * @var ?string
         */
        public readonly ?string $subjectPath = null,

        /**
         * Recipient email body.
         * You can choose to set a static mail body here or use a template path.
         * @var ?string
         */
        public readonly ?string $mailBody = null,

        /**
         * Recipient email body template.
         * You can choose to set a template path here or use a static mail body.
         *
         * @var ?string
         */
        public readonly ?string $mailBodyPath = null,
    ) {
        /**
         * Ensure that either subject or subjectPath is set.
         */
        if ($this->subject === null && $this->subjectPath === null) {
            throw new \InvalidArgumentException('Either subject or subjectPath must be set.');
        }

        /**
         * Ensure that either mailBody or mailBodyPath is set.
         */
        if ($this->mailBody === null && $this->mailBodyPath === null) {
            throw new \InvalidArgumentException('Either mailBody or mailBodyPath must be set.');
        }
    }
}
