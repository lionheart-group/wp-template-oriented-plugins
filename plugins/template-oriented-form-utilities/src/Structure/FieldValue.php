<?php

namespace TofuPlugin\Structure;

/**
 * @property string $field
 * @property mixed $value
 */
class FieldValue
{
    protected array $data = [];

    public function __construct(
        string $field,
        $value,
    )
    {
        $this->data['field'] = $field;
        $this->data['value'] = $value;
    }

    public function updateValue($newValue): void
    {
        $this->data['value'] = $newValue;
    }

    public function __get(string $name)
    {
        return $this->data[$name];
    }

    public function __isset($name): bool
    {
        return isset($this->data[$name]);
    }
}
