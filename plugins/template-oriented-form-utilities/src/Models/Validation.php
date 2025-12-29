<?php

namespace TofuPlugin\Models;

use GUMP;

class Validation
{
    public function validate(Form $form, array $targetValues): void
    {
        $values = $form->getValues();
        $errors = $form->getErrors();

        // Get locale
        $full_locale = get_locale();
        $locale = substr($full_locale, 0, 2);

        // Validate input values
        $gump = new GUMP($locale);
        $gump->validation_rules($form->config->validation->rules);
        $gump->set_fields_error_messages($form->config->validation->messages);
        $sanitizedData = $gump->run($targetValues);

        if ($gump->errors()) {
            $gumpErrors = $gump->get_errors_array();
            foreach ($gumpErrors as $field => $message) {
                $errors->addError($field, $message);
            }
        }

        if (!empty($form->config->validation->after)) {
            $after = $form->config->validation->after;
            $after($form->getValues(), $errors);
        }

        if ($errors->hasErrors()) {
            $sanitizedData = $gump->sanitize($_POST);
        }

        foreach ($sanitizedData as $key => $value) {
            $values->addValue($key, $value);
        }
    }
}
