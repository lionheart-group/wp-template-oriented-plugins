<?php

namespace TofuPlugin\Structure;

class ValidationError
{
    public function __construct(
        /**
         * Field name
         *
         * @var string
         */
        public readonly string $field,

        /**
         * Error message
         *
         * @var string
         */
        public readonly string $message,
    )
    {
    }

    /**
     * Convert to string
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->message;
    }
}
