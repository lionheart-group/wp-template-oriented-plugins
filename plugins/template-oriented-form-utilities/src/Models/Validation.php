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
        $locale = explode('_', $full_locale)[0];

        // Validate input values
        $gump = new GUMP($locale);
        $gump->set_fields_error_messages($form->config->validation->messages);

        // Sanitize and validate
        $sanitizedData = $gump->filter($targetValues, $form->config->validation->filters);
        $isValid = $gump->validate($targetValues, $form->config->validation->rules);

        if ($isValid !== true) {
            // Collect errors
            $gumpErrors = $gump->get_errors_array();
            foreach ($gumpErrors as $field => $message) {
                $errors->addError($field, $message);
            }
        }

        if (!is_array($sanitizedData)) {
            throw new \RuntimeException('Validation failed: sanitized data is not an array.');
        }

        // Collect sanitized values
        foreach ($sanitizedData as $key => $value) {
            $values->addValue($key, $value);
        }

        // After validation hook
        if (!empty($form->config->validation->after)) {
            $after = $form->config->validation->after;
            $after($form->getValues(), $errors);
        }
    }
}
