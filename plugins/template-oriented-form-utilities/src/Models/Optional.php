<?php
/**
 * @via Laravel Optional class concept
 */

namespace TofuPlugin\Models;

use ArrayAccess;

class Optional
{
    /**
     * Create an Optional instance
     *
     * @param mixed $value
     * @return Optional
     */
    public static function of($value): Optional
    {
        return new Optional($value);
    }

    public function __construct(
        /**
         * The wrapped value
         *
         * @var mixed
         */
        protected $value
    )
    {
    }

    /**
     * Magic getter to access properties of the wrapped value.
     *
     * @param string $key
     * @return mixed|null
     */
    public function __get(string $key)
    {
        if (is_object($this->value)) {
            return $this->value->{$key} ?? null;
        }

        if (is_array($this->value)) {
            return $this->value[$key] ?? null;
        }

        return null;
    }

    /**
     * Magic isset to check if a property is set on the wrapped value.
     *
     * @param string $key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        if (is_object($this->value)) {
            return isset($this->value->{$key});
        }

        if (is_array($this->value)) {
            return isset($this->value[$key]);
        }

        return false;
    }

    /**
     * Magic call to invoke methods on the wrapped value.
     *
     * @param string $method
     * @param array $arguments
     * @return mixed|null
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (is_object($this->value) && method_exists($this->value, $method)) {
            return $this->value->{$method}(...$arguments);
        }

        return null;
    }
}
