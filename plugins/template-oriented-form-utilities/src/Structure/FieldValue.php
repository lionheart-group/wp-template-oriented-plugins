<?php

namespace TofuPlugin\Structure;

class FieldValue
{
    protected array $data = [];

    public function __construct(
        string $field,
        mixed $value,
    )
    {
        $this->data['field'] = $field;
        $this->data['value'] = $value;
    }

    public function updateValue(mixed $newValue): void
    {
        $this->data['value'] = $newValue;
    }

    public function __get(string $name): mixed
    {
        return $this->data[$name];
    }

    public function __isset($name)
    {
        return $name === 'field' || $name === 'value';
    }
}
