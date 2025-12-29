<?php

namespace TofuPlugin\Structure;

/**
 * Template configuration class.
 *
 * ```php
 * new ValidationConfig(
 *     rules: [
 *         'name' => 'required|max_len:200',
 *         'email' => 'required|valid_email',
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
 *     after: function ($form, $errors) {
 *         // Get the value of the 'name' field
 *         $value = $form->getValue('name');
 *
 *         // Set the value of the 'foo' field
 *         $form->setValue('foo', 'value');
 *
 *         // Add a custom error message
 *         $errors->add('name', 'This is a custom error message.');
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
         *     'name' => [
         *         'required' => [],
         *     ],
         *     'email' => [
         *         'required' => [],
         *         'email' => [],
         *     ],
         * ],
         * ```
         *
         * @var array
         */
        public readonly array $rules = [],

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
         */
        public readonly \Closure | null $after = null,
    ) {}
}
