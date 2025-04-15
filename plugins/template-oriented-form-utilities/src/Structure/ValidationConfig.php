<?php

namespace TofuPlugin\Structure;

/**
 * Template configuration class.
 *
 * @package TofuPlugin\Structure
 */
class ValidationConfig
{
    public function __construct(
        /**
         * Custom after hook
         */
        public readonly \Closure | null $after = null,
    ) {}
}
