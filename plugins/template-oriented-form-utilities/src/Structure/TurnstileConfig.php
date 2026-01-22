<?php

namespace TofuPlugin\Structure;

class TurnstileConfig
{
    public function __construct(
        /**
         * Site key for Turnstile.
         *
         * @var string
         */
        public readonly string $siteKey,

        /**
         * Secret key for Turnstile.
         *
         * @var string
         */
        public readonly string $secretKey,
    )
    {
    }
}
