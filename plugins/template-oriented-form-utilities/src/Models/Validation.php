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
        $gump->filter_rules($form->config->validation->filters);
        $gump->set_fields_error_messages($form->config->validation->messages);
        $sanitizedData = $gump->run($targetValues);

        if ($gump->errors()) {
            // Collect errors
            $gumpErrors = $gump->get_errors_array();
            foreach ($gumpErrors as $field => $message) {
                $errors->addError($field, $message);
            }

            // If validation fails, sanitize directly from $targetValues
            $sanitizedData = $gump->sanitize($targetValues);
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
