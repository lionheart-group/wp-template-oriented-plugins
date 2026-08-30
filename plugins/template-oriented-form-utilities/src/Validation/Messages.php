<?php

namespace TofuPlugin\Validation;

/**
 * Every validation message the engine can emit, keyed by the rule's message
 * key.
 *
 * All strings live here, in literal __() calls, for two reasons: it is the
 * only form `wp i18n make-pot` can extract, and it keeps the full set of
 * user-facing validation text reviewable in one place rather than scattered
 * across seventy rule classes.
 *
 * Placeholders are `:attribute` for the field name (or its `names` alias)
 * and `:<paramName>` for each of the rule's parameters. Translations must
 * keep the placeholders intact but may reorder them.
 *
 * House style, applied throughout:
 *   - address the field directly — ":attribute must ..." / ":attribute cannot ..."
 *   - no trailing full stop; these sit beside an input, not in prose
 *   - say what is wanted rather than what went wrong, where that reads naturally
 *
 * NOTE: all() must not be called before the `init` hook — __() cannot
 * translate until the textdomain is loaded. Validation always runs well
 * after that, and this is why the messages are resolved lazily per request
 * rather than held in a property.
 */
final class Messages
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'rule.accepted'                 => __('Please confirm :attribute', 'template-oriented-form-utilities'),
            'rule.after'                    => __(':attribute must be later than :time', 'template-oriented-form-utilities'),
            'rule.alpha'                    => __(':attribute must contain letters only', 'template-oriented-form-utilities'),
            'rule.alpha_dash'               => __(':attribute must contain letters, numbers, hyphens and underscores only', 'template-oriented-form-utilities'),
            'rule.alpha_num'                => __(':attribute must contain letters and numbers only', 'template-oriented-form-utilities'),
            'rule.alpha_spaces'             => __(':attribute must contain letters and spaces only', 'template-oriented-form-utilities'),
            'rule.any_of'                   => __('Every entry in :attribute must be one of :allowed_values', 'template-oriented-form-utilities'),
            'rule.array'                    => __(':attribute must be a list of values', 'template-oriented-form-utilities'),
            'rule.array_can_only_have_keys' => __(':attribute accepts only these keys: :keys', 'template-oriented-form-utilities'),
            'rule.array_must_have_keys'     => __(':attribute is missing one or more required keys: :keys', 'template-oriented-form-utilities'),
            'rule.before'                   => __(':attribute must be earlier than :time', 'template-oriented-form-utilities'),
            'rule.between'                  => __(':attribute must be between :min and :max', 'template-oriented-form-utilities'),
            'rule.boolean'                  => __(':attribute must be either true or false', 'template-oriented-form-utilities'),
            'rule.date'                     => __('Enter :attribute as a valid date', 'template-oriented-form-utilities'),
            'rule.default'                  => __(':attribute is not valid', 'template-oriented-form-utilities'),
            'rule.default_value'            => __(':attribute falls back to :default', 'template-oriented-form-utilities'),
            'rule.different'                => __(':attribute must not match :field', 'template-oriented-form-utilities'),
            'rule.digits'                   => __(':attribute must be exactly :length digits', 'template-oriented-form-utilities'),
            'rule.digits_between'           => __(':attribute must be between :min and :max digits', 'template-oriented-form-utilities'),
            'rule.email'                    => __('Enter :attribute as a valid email address', 'template-oriented-form-utilities'),
            'rule.ends_with'                => __(':attribute must end with :compare_with', 'template-oriented-form-utilities'),
            'rule.exists'                   => __(':attribute does not match any existing record', 'template-oriented-form-utilities'),
            'rule.extension'                => __(':attribute must be one of these file types: :allowed_extensions', 'template-oriented-form-utilities'),
            'rule.float'                    => __(':attribute must be a decimal number', 'template-oriented-form-utilities'),
            'rule.in'                       => __(':attribute must be one of :allowed_values', 'template-oriented-form-utilities'),
            'rule.integer'                  => __(':attribute must be a whole number', 'template-oriented-form-utilities'),
            'rule.ip'                       => __('Enter :attribute as a valid IP address', 'template-oriented-form-utilities'),
            'rule.ipv4'                     => __('Enter :attribute as a valid IPv4 address', 'template-oriented-form-utilities'),
            'rule.ipv6'                     => __('Enter :attribute as a valid IPv6 address', 'template-oriented-form-utilities'),
            'rule.json'                     => __(':attribute must be valid JSON', 'template-oriented-form-utilities'),
            'rule.length'                   => __(':attribute must be exactly :length characters', 'template-oriented-form-utilities'),
            'rule.lowercase'                => __(':attribute must be lowercase', 'template-oriented-form-utilities'),
            'rule.max'                      => __(':attribute may not be greater than :max', 'template-oriented-form-utilities'),
            'rule.mimes'                    => __(':attribute must be one of these file types: :allowed_types', 'template-oriented-form-utilities'),
            'rule.min'                      => __(':attribute may not be less than :min', 'template-oriented-form-utilities'),
            'rule.not_in'                   => __(':attribute may not be any of :disallowed_values', 'template-oriented-form-utilities'),
            'rule.numeric'                  => __(':attribute must be a number', 'template-oriented-form-utilities'),
            'rule.phone_number'             => __('Enter :attribute in international format, such as +81312345678', 'template-oriented-form-utilities'),
            'rule.present'                  => __(':attribute is missing from the submission', 'template-oriented-form-utilities'),
            'rule.prohibited'               => __(':attribute cannot be used here', 'template-oriented-form-utilities'),
            'rule.prohibited_if'            => __(':attribute cannot be used when :field is :values', 'template-oriented-form-utilities'),
            'rule.prohibited_unless'        => __(':attribute can only be used when :field is :values', 'template-oriented-form-utilities'),
            'rule.prohibited_with'          => __(':attribute cannot be used together with :fields', 'template-oriented-form-utilities'),
            'rule.prohibited_with_all'      => __(':attribute cannot be used when all of :fields are filled in', 'template-oriented-form-utilities'),
            'rule.prohibited_without'       => __(':attribute can only be used once :fields are filled in', 'template-oriented-form-utilities'),
            'rule.prohibited_without_all'   => __(':attribute can only be used once all of :fields are filled in', 'template-oriented-form-utilities'),
            'rule.regex'                    => __(':attribute is not in the expected format', 'template-oriented-form-utilities'),
            'rule.rejected'                 => __(':attribute must be declined', 'template-oriented-form-utilities'),
            'rule.required'                 => __(':attribute is required', 'template-oriented-form-utilities'),
            'rule.required_if'              => __(':attribute is required when :field is :values', 'template-oriented-form-utilities'),
            'rule.required_unless'          => __(':attribute is required unless :field is :values', 'template-oriented-form-utilities'),
            'rule.required_with'            => __(':attribute is required when :fields is filled in', 'template-oriented-form-utilities'),
            'rule.required_with_all'        => __(':attribute is required when all of :fields are filled in', 'template-oriented-form-utilities'),
            'rule.required_without'         => __(':attribute is required when :fields is left blank', 'template-oriented-form-utilities'),
            'rule.required_without_all'     => __(':attribute is required when all of :fields are left blank', 'template-oriented-form-utilities'),
            'rule.requires'                 => __(':attribute also needs :fields to be filled in', 'template-oriented-form-utilities'),
            'rule.same'                     => __(':attribute must match :field', 'template-oriented-form-utilities'),
            'rule.starts_with'              => __(':attribute must start with :compare_with', 'template-oriented-form-utilities'),
            'rule.string'                   => __(':attribute must be text', 'template-oriented-form-utilities'),
            'rule.unique'                   => __(':attribute must be unique — :value is already in use', 'template-oriented-form-utilities'),
            'rule.uppercase'                => __(':attribute must be uppercase', 'template-oriented-form-utilities'),
            'rule.url'                      => __('Enter :attribute as a valid URL', 'template-oriented-form-utilities'),
            'rule.uuid'                     => __('Enter :attribute as a valid UUID', 'template-oriented-form-utilities'),
            'rule.required_file'            => __('Please choose a file for :attribute', 'template-oriented-form-utilities'),
            'rule.max_mb'                   => __(':attribute must be no larger than :max_mb MB', 'template-oriented-form-utilities'),
            'rule.mime_type'                => __(':attribute is not an accepted file type', 'template-oriented-form-utilities'),
            'rule.uploaded_file'            => __(':attribute was not uploaded successfully', 'template-oriented-form-utilities'),
            'rule.uploaded_file.min_size'   => __(':attribute is too small; the minimum is :min_size', 'template-oriented-form-utilities'),
            'rule.uploaded_file.max_size'   => __(':attribute is too large; the maximum is :max_size', 'template-oriented-form-utilities'),
            'rule.uploaded_file.type'       => __(':attribute is not one of the accepted file types: :allowed_types', 'template-oriented-form-utilities'),
        ];
    }
}
