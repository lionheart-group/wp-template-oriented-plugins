<?php

namespace TofuPlugin\Structure;

use TofuPlugin\Models\FieldValueCollection;
use TofuPlugin\Models\ValidationErrorCollection;

/**
 * Template configuration class.
 *
 * ```php
 * new ValidationConfig(
 *     allows: [
 *         'name',
 *         'email',
 *     ],
 *     rules: [
 *         'name' => 'required|max:200',
 *         'email' => 'required|email',
 *     ],
 *     messages: [
 *         'name' => [
 *             'required' => 'The name field is required.',
 *             'max' => 'The name must be maximum 200 characters.',
 *         ],
 *         'email' => [
 *             'required' => 'The email field is required.',
 *             'email' => 'The email must be a valid email address.',
 *         ],
 *     ],
 *     after: function ($values, $errors) {
 *         // Get the value of the 'name' field
 *         $value = $values->getValue('name');
 *
 *         // Set the value of the 'foo' field
 *         $values->addValue('foo', 'value');
 *
 *         // Add a custom error message
 *         $errors->addError('name', 'This is a custom error message.');
 *     }
 * );
 * ```
 *
 * @package TofuPlugin\Structure
 */
class ValidationConfig
{
    public function __construct(
        /**
         * Allowed field names.
         *
         * If not set, the specified fields do not store values in the session.
         *
         * ```php
         * allows: [
         *     'name',
         *     'email',
         * ],
         * ```
         *
         * @var array
         */
        public readonly array $allows,

        /**
         * Validation rules.
         *
         * ```php
         * rules: [
         *     'name' => 'required|max:200',
         *     'email' => 'required|email',
         * ],
         * ```
         *
         * @var array
         * @via https://github.com/somnambulist-tech/validation?tab=readme-ov-file#available-rules
         */
        public readonly array $rules,

        /**
         * Field names for error messages.
         *
         * ```php
         * names: [
         *     'name' => 'Full Name',
         *     'email' => 'Email Address',
         * ]
         * ```
         */
        public readonly array $names,

        /**
         * Validation messages.
         *
         * ```php
         * messages: [
         *     'name' => [
         *         'required' => 'The name field is required.',
         *         'max' => 'The name must be maximum 200 characters.',
         *     ],
         *     'email' => [
         *         'required' => 'The email field is required.',
         *         'email' => 'The email must be a valid email address.',
         *     ],
         * ],
         * ```
         *
         * @var array
         */
        public readonly array $messages = [],

        /**
         * Custom after hook
         *
         * @var ?\Closure(FieldValueCollection $values, ValidationErrorCollection $errors):void
         */
        public readonly ?\Closure $after = null,
    ) {}
}
