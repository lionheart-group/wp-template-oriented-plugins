<?php

namespace TofuPlugin\Structure;

use TofuPlugin\Models\FieldValueCollection;
use TofuPlugin\Models\ValidationErrorCollection;

/**
 * Template configuration class.
 *
 * ```php
 * new ValidationConfig(
 *     rules: [
 *         'name' => 'required|max_len:200',
 *         'email' => 'required|valid_email',
 *     ],
 *     filters: [
 *         'name' => 'trim|sanitize_string',
 *         'email' => 'trim|sanitize_email',
 *     ],
 *     messages: [
 *         'name' => [
 *             'required' => 'The name field is required.',
 *             'max_len' => 'The name must be maximum 200 characters.',
 *         ],
 *         'email' => [
 *             'required' => 'The email field is required.',
 *             'valid_email' => 'The email must be a valid email address.',
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
         * Validation rules.
         *
         * ```php
         * rules: [
         *     'name' => 'required|max_len:200',
         *     'email' => 'required|valid_email',
         * ],
         * ```
         *
         * @var array
         * @via https://github.com/Wixel/GUMP?tab=readme-ov-file#available-validators
         */
        public readonly array $rules = [],

        /**
         * Filtering rules.
         *
         * ```php
         * filters: [
         *     'name' => 'trim|sanitize_string',
         *     'email' => 'trim|sanitize_email',
         * ],
         * ```
         *
         * @var array
         * @via https://github.com/Wixel/GUMP?tab=readme-ov-file#available-filters
         */
        public readonly array $filters = [],

        /**
         * Validation messages.
         *
         * ```php
         * messages: [
         *     'name' => [
         *         'required' => 'The name field is required.',
         *         'length' => 'The name must be between {min} and {max} characters.',
         *     ],
         *     'email' => [
         *         'required' => 'The email field is required.',
         *         'valid_email' => 'The email must be a valid email address.',
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
         * @var \Closure(FieldValueCollection $values, ValidationErrorCollection $errors): void|null
         */
        public readonly \Closure | null $after = null,
    ) {}
}
