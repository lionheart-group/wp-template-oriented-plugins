<?php
/**
 * @via Laravel Optional class concept
 */

namespace TofuPlugin\Models;

use ArrayAccess;

class Optional
{
    public static function of($value): Optional
    {
        return new Optional($value);
    }

    public function __construct(
        protected $value
    )
    {
    }

    public function __get($key)
    {
        if (is_object($this->value)) {
            return $this->value->{$key} ?? null;
        }

        if (is_array($this->value)) {
            return $this->value[$key] ?? null;
        }

        return null;
    }

    public function __isset($key)
    {
        if (is_object($this->value)) {
            return isset($this->value->{$key});
        }

        if (is_array($this->value)) {
            return isset($this->value[$key]);
        }

        return false;
    }

    public function __call($method, $arguments)
    {
        if (is_object($this->value) && method_exists($this->value, $method)) {
            return $this->value->{$method}(...$arguments);
        }

        return null;
    }
}
