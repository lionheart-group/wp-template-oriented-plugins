<?php

namespace TofuPlugin\Structure;

class MailRecipientsCollection
{
    /**
     * @param MailRecipientsConfig[] $recipients
     */
    public function __construct(
        /**
         * @var MailRecipientsConfig[]
         */
        public readonly array $recipients,
    ) {
        foreach ($recipients as $recipient) {
            if (!$recipient instanceof MailRecipientsConfig) {
                throw new \InvalidArgumentException('Invalid recipient configuration');
            }
        }
    }
}
