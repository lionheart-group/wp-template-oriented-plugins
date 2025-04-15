<?php

namespace TofuPlugin\Structure;

/**
 * Class Config
 *
 * This class is used to define the configuration for a form item.
 *
 * @package TofuPlugin\Structure
 */
class Config
{
    public function __construct(
        /**
         * Key for the form item.
         *
         * @var string
         */
        public readonly string $key,

        /**
         * Input page path.
         *
         * @var string
         */
        public readonly string $inputPath,

        /**
         * Confirm page path.
         * If you want to skip the confirmation page, set this to null.
         *
         * @var string|null
         */
        public readonly string | null $confirmPath = null,

        /**
         * Result page path.
         *
         * @var string
         */
        public readonly string $resultPath,
    )
    {
    }
}
