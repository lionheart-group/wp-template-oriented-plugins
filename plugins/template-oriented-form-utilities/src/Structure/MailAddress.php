<?php

namespace TofuPlugin\Structure;

use TofuPlugin\Helpers\Validate;

class MailAddress
{
    public function __construct(
        public readonly string $email,
        public readonly string $name = '',
    )
    {
        if (Validate::isValidEmail($this->email) === false) {
            throw new \InvalidArgumentException("Invalid email address.");
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
