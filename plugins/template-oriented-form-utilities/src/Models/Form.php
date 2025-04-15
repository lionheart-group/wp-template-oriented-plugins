<?php

namespace TofuPlugin\Models;

use TofuPlugin\Structure\FormConfig;

class Form
{
    public function __construct(
        /**
         * Configuration for the form.
         *
         * @var FormConfig
         */
        protected readonly FormConfig $config,
    )
    {}

    public function getKey()
    {
        return $this->config->key;
    }
}
