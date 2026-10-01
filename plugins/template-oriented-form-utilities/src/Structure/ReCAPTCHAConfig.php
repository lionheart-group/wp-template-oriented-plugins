<?php

namespace TofuPlugin\Structure;

class ReCAPTCHAConfig
{
    public function __construct(
        /**
         * Site key for reCAPTCHA.
         *
         * @var string
         */
        public readonly string $siteKey,

        /**
         * Secret key for reCAPTCHA.
         *
         * @var string
         */
        public readonly string $secretKey,

        /**
         * Threshold score for reCAPTCHA v3.
         *
         * @var float
         */
        public readonly float $threshold = 0.5,
    )
    {
    }
}
