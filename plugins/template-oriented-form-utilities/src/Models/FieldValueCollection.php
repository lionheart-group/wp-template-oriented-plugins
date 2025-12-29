<?php

namespace TofuPlugin\Models;

use TofuPlugin\Structure\FieldValue;

class FieldValueCollection
{
    /**
     * Field values collection
     *
     * @var FieldValue[]
     */
    private array $values = [];

    public function __construct()
    {
    }

    /**
     * Get all field values
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
        $currentValue = $this->getValue($field);
        if ($currentValue !== null) {
            $currentValue->updateValue($value);
            return;
        }

        $this->values[] = new FieldValue(
            field: $field,
            value: $value,
        );
    }

    /**
     * Check if there are values for a specific field
     *
     * @param string $field
     * @return boolean
     */
    public function hasValue(string $field): bool
    {
        foreach ($this->values as $value) {
            if ($value->field === $field) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get field value for a specific field
     *
     * @param string $field
     * @return FieldValue|null
     */
    public function getValue(string $field): FieldValue | null
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
