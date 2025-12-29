<?php

namespace TofuPlugin\Structure;

class ValidationError
{
    public function __construct(
        public readonly string $field,
        public readonly string $message,
    )
    {
    }

    public function __toString(): string
    {
        return $this->message;
    }
}
