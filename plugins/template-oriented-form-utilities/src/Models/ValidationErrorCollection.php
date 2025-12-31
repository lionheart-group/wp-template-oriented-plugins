<?php

namespace TofuPlugin\Models;

use TofuPlugin\Structure\ValidationError;

class ValidationErrorCollection
{
    /**
     * Error messages collection
     *
     * @var ValidationError[]
     */
    private array $errors = [];

    public function __construct()
    {
    }

    /**
     * Check if there are any validation errors
     *
     * @return boolean
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get all validation errors
     *
     * @return ValidationError[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all error messages as strings
     *
     * @return string[]
     */
    public function getErrorMessages(): array
    {
        return array_map(fn($error) => (string) $error, array_values($this->errors));
    }

    /**
     * Add a validation error message
     *
     * @param string $field
     * @param string $message
     * @return void
     */
    public function addError(string $field, string $message): void
    {
        $this->errors[] = new ValidationError($field, $message);
    }

    /**
     * Check if there are errors for a specific field
     *
     * @param string $field
     * @return boolean
     */
    public function hasFieldErrors(string $field): bool
    {
        foreach ($this->errors as $error) {
            if ($error->field === $field) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get error messages for a specific field
     *
     * @param string $field
     * @return string[]
     */
    public function getFieldErrorMessages(string $field): array
    {
        $messages = [];
        foreach ($this->errors as $error) {
            if ($error->field === $field) {
                $messages[] = (string) $error;
            }
        }
        return $messages;
    }

    /**
     * Convert errors to an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $array = [];
        foreach ($this->errors as $error) {
            if (!isset($array[$error->field])) {
                $array[$error->field] = [];
            }
            $array[$error->field][] = $error->message;
        }
        return $array;
    }
}
