<?php

namespace TofuPlugin\Structure;

class MailAddress
{
    public function __construct(
        /**
         * Email address
         *
         * @var string
         */
        public readonly string $email,

        /**
         * Name
         *
         * @var string
         */
        public readonly string $name = '',
    )
    {
        // Validate the email address using WordPress's built-in function.
        if (!is_email($email)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }
    }

    /**
     * Convert to string
     *
     * If name is provided, format as "Name <email>", otherwise just return the email.
     * If name is empty, only the email address is returned.
     *
     * @return string
     */
    public function __toString(): string
    {
        if ($this->name !== '') {
            return sprintf('%s <%s>', $this->name, $this->email);
        }
        return $this->email;
    }
}
