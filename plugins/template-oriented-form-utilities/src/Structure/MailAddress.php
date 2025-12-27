<?php

namespace TofuPlugin\Structure;

class MailAddress
{
    public function __construct(
        public readonly string $email,
        public readonly string $name = '',
    )
    {
    }

    public function __toString()
    {
        if ($this->name !== '') {
            return sprintf('%s <%s>', $this->name, $this->email);
        }
        return $this->email;
    }
}
