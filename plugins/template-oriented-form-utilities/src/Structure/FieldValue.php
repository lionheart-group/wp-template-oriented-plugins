<?php

namespace TofuPlugin\Structure;

/**
 * @property string $field
 * @property mixed $value
 */
class FieldValue
{
    /**
     * Internal data storage
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    public function __construct(
        /**
         * Field name
         *
         * @var string
         */
        string $field,

        /**
         * Field value
         *
         * @var mixed
         */
        $value,
    )
    {
        $this->data['field'] = $field;
        $this->data['value'] = $value;
    }

    /**
     * Update the field value
     *
     * @param mixed $newValue
     * @return void
     */
    public function updateValue($newValue): void
    {
        $this->data['value'] = $newValue;
    }

    /**
     * Magic getter to access properties
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->data[$name];
    }

    /**
     * Magic isset to check if a property is set
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name): bool
    {
        return isset($this->data[$name]);
    }
}
