<?php

namespace TofuPlugin\Models;

use TofuPlugin\Structure\FieldValue;

class FieldValueCollection
{
    /**
     * Error messages collection
     *
     * @var FieldValue[]
     */
    private array $values = [];

    public function __construct()
    {
    }

    /**
     * Get all validation values
     *
     * @return FieldValue[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Add a field value
     *
     * @param string $field
     * @param mixed $value
     * @return void
     */
    public function addValue(string $field, mixed $value): void
    {
        // Field value isn't duplicated
        $currentValue = $this->getFieldValue($field);
        if ($currentValue !== null) {
            $currentValue->updateValue($value);
            return;
        }

        $this->values[] = new FieldValue($field, $value);
    }

    /**
     * Check if there are values for a specific field
     *
     * @param string $field
     * @return boolean
     */
    public function hasFieldValue(string $field): bool
    {
        foreach ($this->values as $error) {
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
     * @return FieldValue|null
     */
    public function getFieldValue(string $field): FieldValue | null
    {
        foreach ($this->values as $value) {
            if ($value->field === $field) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Convert values to an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $array = [];
        foreach ($this->values as $value) {
            $array[$value->field] = $value->value;
        }
        return $array;
    }
}
