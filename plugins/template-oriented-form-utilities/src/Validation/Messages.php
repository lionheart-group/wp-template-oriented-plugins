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
 * The English text is inherited verbatim from somnambulist/validation 1.13.0
 * (MIT), the library this engine replaced, so that error text does not shift
 * under sites already running TOFU.
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
            'rule.accepted'                 => __(':attribute must be one of: :accepted', 'template-oriented-form-utilities'),
            'rule.after'                    => __(':attribute must be a date after :time', 'template-oriented-form-utilities'),
            'rule.alpha'                    => __(':attribute only allows alphabetic characters', 'template-oriented-form-utilities'),
            'rule.alpha_dash'               => __(':attribute only allows a-z, 0-9, _ and -', 'template-oriented-form-utilities'),
            'rule.alpha_num'                => __(':attribute only allows alphabetic and numeric characters', 'template-oriented-form-utilities'),
            'rule.alpha_spaces'             => __(':attribute may only contain alphabetic characters and spaces', 'template-oriented-form-utilities'),
            'rule.any_of'                   => __('Each value in :attribute must be one of :allowed_values', 'template-oriented-form-utilities'),
            'rule.array'                    => __(':attribute must be an array', 'template-oriented-form-utilities'),
            'rule.array_can_only_have_keys' => __(':attribute must only have the following keys: :keys', 'template-oriented-form-utilities'),
            'rule.array_must_have_keys'     => __(':attribute must specify all of the following keys: :keys', 'template-oriented-form-utilities'),
            'rule.before'                   => __(':attribute must be a date before :time.', 'template-oriented-form-utilities'),
            'rule.between'                  => __(':attribute must be between :min and :max', 'template-oriented-form-utilities'),
            'rule.boolean'                  => __(':attribute must be a boolean', 'template-oriented-form-utilities'),
            // TOFU's own file rules. Their English text
            // predates this catalogue and is kept word for word so existing
            // sites see no change; the Japanese is new, replacing the
            // English that used to leak through on ja sites because these
            // three keys had no translation at all.
            'rule.custom_required_file'     => __('The :attribute field is required.', 'template-oriented-form-utilities'),
            'rule.max_mb'                   => __('The :attribute field must be less than :max_mb MB in size.', 'template-oriented-form-utilities'),
            'rule.mime_type'                => __('The :attribute field must be a file of an allowed type.', 'template-oriented-form-utilities'),

            'rule.date'                     => __(':attribute is not valid date format', 'template-oriented-form-utilities'),
            'rule.default'                  => __(':attribute is not valid', 'template-oriented-form-utilities'),
            'rule.default_value'            => __(':attribute default is :default', 'template-oriented-form-utilities'),
            'rule.different'                => __(':attribute must be different to :field', 'template-oriented-form-utilities'),
            'rule.digits'                   => __(':attribute must be numeric and must have an exact length of :length', 'template-oriented-form-utilities'),
            'rule.digits_between'           => __(':attribute be numeric and must have a length between :min and :max', 'template-oriented-form-utilities'),
            'rule.email'                    => __(':attribute is not a valid email address', 'template-oriented-form-utilities'),
            'rule.ends_with'                => __(':attribute must end with :compare_with', 'template-oriented-form-utilities'),
            'rule.exists'                   => __(':attribute must match an existing record', 'template-oriented-form-utilities'),
            'rule.extension'                => __(':attribute must be a :allowed_extensions file', 'template-oriented-form-utilities'),
            'rule.float'                    => __(':attribute must be a floating point number', 'template-oriented-form-utilities'),
            'rule.in'                       => __(':attribute must be one of :allowed_values', 'template-oriented-form-utilities'),
            'rule.integer'                  => __(':attribute must be integer', 'template-oriented-form-utilities'),
            'rule.ip'                       => __(':attribute must be a valid IP address', 'template-oriented-form-utilities'),
            'rule.ipv4'                     => __(':attribute must be a valid IPv4 address', 'template-oriented-form-utilities'),
            'rule.ipv6'                     => __(':attribute must be a valid IPv6 address', 'template-oriented-form-utilities'),
            'rule.json'                     => __(':attribute must be a valid JSON string', 'template-oriented-form-utilities'),
            'rule.length'                   => __(':attribute must be a string of exactly :length characters', 'template-oriented-form-utilities'),
            'rule.lowercase'                => __(':attribute must be lowercase', 'template-oriented-form-utilities'),
            'rule.max'                      => __(':attribute maximum is :max', 'template-oriented-form-utilities'),
            'rule.mimes'                    => __(':attribute file type must be :allowed_types', 'template-oriented-form-utilities'),
            'rule.min'                      => __(':attribute minimum is :min', 'template-oriented-form-utilities'),
            'rule.not_in'                   => __(':attribute must not be one of :disallowed_values', 'template-oriented-form-utilities'),
            'rule.numeric'                  => __(':attribute must be numeric', 'template-oriented-form-utilities'),
            'rule.phone_number'             => __(':attribute is not a valid E.164 phone number', 'template-oriented-form-utilities'),
            'rule.present'                  => __(':attribute must be present', 'template-oriented-form-utilities'),
            'rule.prohibited'               => __(':attribute is not allowed', 'template-oriented-form-utilities'),
            'rule.prohibited_if'            => __(':attribute is not allowed if :field has value(s) :values', 'template-oriented-form-utilities'),
            'rule.prohibited_unless'        => __(':attribute is not allowed if :field does not have value(s) :values', 'template-oriented-form-utilities'),
            'rule.prohibited_with'          => __(':attribute is not allowed with :fields', 'template-oriented-form-utilities'),
            'rule.prohibited_with_all'      => __(':attribute is not allowed with all of :fields', 'template-oriented-form-utilities'),
            'rule.prohibited_without'       => __(':attribute is not allowed when one of the following fields is absent: :fields', 'template-oriented-form-utilities'),
            'rule.prohibited_without_all'   => __(':attribute is not allowed when all of the following fields are absents: :fields', 'template-oriented-form-utilities'),
            'rule.regex'                    => __(':attribute does not meet required format', 'template-oriented-form-utilities'),
            'rule.rejected'                 => __(':attribute must be one of: :rejected', 'template-oriented-form-utilities'),
            'rule.required'                 => __(':attribute is required', 'template-oriented-form-utilities'),
            'rule.required_if'              => __(':attribute is required if :field has a value of :values', 'template-oriented-form-utilities'),
            'rule.required_unless'          => __(':attribute is required if :field has one of :values', 'template-oriented-form-utilities'),
            'rule.required_with'            => __(':attribute is required with :fields', 'template-oriented-form-utilities'),
            'rule.required_with_all'        => __(':attribute is required with all of :fields', 'template-oriented-form-utilities'),
            'rule.required_without'         => __(':attribute is required when :fields are empty', 'template-oriented-form-utilities'),
            'rule.required_without_all'     => __(':attribute is required when :fields are all empty', 'template-oriented-form-utilities'),
            'rule.requires'                 => __(':attribute requires :fields', 'template-oriented-form-utilities'),
            'rule.same'                     => __(':attribute must be the same as :field', 'template-oriented-form-utilities'),
            'rule.starts_with'              => __(':attribute must start with :compare_with', 'template-oriented-form-utilities'),
            'rule.string'                   => __(':attribute must be a string', 'template-oriented-form-utilities'),
            'rule.unique'                   => __(':attribute must be unique, :value already exists', 'template-oriented-form-utilities'),
            'rule.uploaded_file'            => __(':attribute is not a valid uploaded file', 'template-oriented-form-utilities'),
            'rule.uploaded_file.max_size'   => __(':attribute file is too large, maximum size is :max_size', 'template-oriented-form-utilities'),
            'rule.uploaded_file.min_size'   => __(':attribute file is too small, minimum size is :min_size', 'template-oriented-form-utilities'),
            // Shares its English wording with rule.mimes but is translated
            // separately, so it needs a context to stay a distinct msgid.
            'rule.uploaded_file.type'       => _x(':attribute file type must be :allowed_types', 'uploaded_file rule', 'template-oriented-form-utilities'),
            'rule.uppercase'                => __(':attribute must be uppercase', 'template-oriented-form-utilities'),
            'rule.url'                      => __(':attribute is not a valid URL', 'template-oriented-form-utilities'),
            'rule.uuid'                     => __(':attribute is not a valid UUID or is NIL', 'template-oriented-form-utilities'),
        ];
    }
}
