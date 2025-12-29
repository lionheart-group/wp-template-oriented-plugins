<?php

namespace TofuPlugin\Structure;

class FieldValue
{
    public function __construct(
        protected string $_field,
        protected mixed $_value,
    )
    {
    }

    public function updateValue(mixed $newValue): void
    {
        $this->_value = $newValue;
    }

    public function __get(string $name): mixed
    {
        switch ($name) {
            case 'field':
                return $this->_field;
            case 'value':
                return $this->_value;
            default:
                throw new \InvalidArgumentException("Undefined property: " . $name);
        }
    }
}
