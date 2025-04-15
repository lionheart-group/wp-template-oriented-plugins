<?php

namespace TofuPlugin\Helpers;

use TofuPlugin\Structure\FormConfig;

class Form
{
    /**
     * Form list
     *
     * @var \TofuPlugin\Models\Form[]
     */
    protected static $forms = [];

    public static function setup(FormConfig $config)
    {
        // Check if the form is already registered
        foreach (self::$forms as $form) {
            if ($form->getKey() === $config->key) {
                wp_die(
                    sprintf('Form with key "%s" is already registered.', $config->key),
                    'TOFU Form Registration Error',
                    ['response' => 500]
                );

                return;
            }
        }

        self::$forms[] = new \TofuPlugin\Models\Form($config);
    }
}
