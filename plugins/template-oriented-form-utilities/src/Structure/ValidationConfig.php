<?php

namespace TofuPlugin\Structure;

/**
 * Template configuration class.
 *
 * ```php
 * new ValidationConfig(
 *     rules: [
 *         'name' => [
 *             'required' => true,
 *         ],
 *         'email' => [
 *             'required' => true,
 *             'email' => true,
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
         *         'required' => true,
         *     ],
         *     'email' => [
         *         'required' => true,
         *         'email' => true,
         *     ],
         * ],
         * ```
         *
         * @var array
         */
        public readonly array $rules = [],

        /**
         * Custom after hook
         */
        public readonly \Closure | null $after = null,
    ) {}
}
