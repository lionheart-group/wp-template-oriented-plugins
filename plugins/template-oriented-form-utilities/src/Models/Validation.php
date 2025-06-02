<?php

namespace TofuPlugin\Models;

use TofuPlugin\Helpers\Validate;

class Validation
{
    public static function validate($rules, $values)
    {
        $validated = $values;
        foreach ($rules as $key => $rule) {
            $value = $values[$key] ?? null;

            // Check for required fields
            if (!empty($rule['required']) && empty($value)) {
                $errors[$key] = sprintf('Field "%s" is required.', $key);
                continue;
            }

            // Check for valid email
            if (!empty($rule['email']) && !Validate::isValidEmail($value)) {
                $errors[$key] = sprintf('Field "%s" must be a valid email address.', $key);
                continue;
            }

            // Check if number
            if (!empty($rule['number']) && !Validate::isValidNumber($value)) {
                $errors[$key] = sprintf('Field "%s" must be a number.', $key);
                continue;
            }

            // Check min
            if (!empty($rule['min']) && !Validate::isValidLength($value, $rule['min'])) {
                $errors[$key] = sprintf('Field "%s" length must be greater than or equals to %s.', $key, $rule['min']);
                continue;
            }

            // Check max
            if (!empty($rule['max']) && !Validate::isValidLength($value, null, $rule['max'])) {
                $errors[$key] = sprintf('Field "%s" length must be less than or equals to %s.', $key, $rule['max']);
                continue;
            }

            // Check if valid phone number
            if (!empty($rule['phone']) && !Validate::isValidPhone($value)) {
                $errors[$key] = sprintf('Field "%s" must be a valid phone number.', $key);
                continue;
            }

            // Check if valid code
            if (!empty($rule['code']) && !Validate::isValidCode($value)) {
                $errors[$key] = sprintf('Field "%s" must be a valid code.', $key);
                continue;
            }

            // Check if valid size
            if (!empty($rule['file_size']) && !Validate::isValidCode($value, $rule['file_size'])) {
                $errors[$key] = sprintf('File "%s" must be at least "%s".', $key, $rule['file_size']);
                continue;
            }
        }

        $validated['errors'] = $errors ?? null;
        return $validated;
    }
}
