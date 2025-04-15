<?php

namespace TofuPlugin\Structure;

/**
 * Template configuration class.
 *
 * @package TofuPlugin\Structure
 */
class TemplateConfig
{
    public function __construct(
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

        /**
         * Error page path.
         * If you want to display the input page, set this to null.
         *
         * @var string|null
         */
        public readonly string | null $errorPath = null,
    ) {}
}
