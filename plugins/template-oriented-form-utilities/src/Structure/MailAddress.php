<?php

namespace TofuPlugin\Structure;

use GUMP;

class MailAddress
{
    public function __construct(
        public readonly string $email,
        public readonly string $name = '',
    )
    {
        $gump = GUMP::get_instance();

        // Validate the email addresses.
        if (
            $gump->is_valid(
                ['email' => $email],
                ['email' => 'required|valid_email']
            ) !== true
        ) {
            throw new \InvalidArgumentException('Invalid email address.');
        }
    }

    public function __toString(): string
    {
        if ($this->name !== '') {
            return sprintf('%s <%s>', $this->name, $this->email);
        }
        return $this->email;
    }
}
