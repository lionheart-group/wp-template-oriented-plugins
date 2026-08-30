<?php

namespace TofuPlugin\Validation;

/**
 * Builds a configured Validator.
 *
 * This is the only place that knows which rule names exist, so adding a rule
 * is a one-line change here plus the class itself.
 */
class ValidatorFactory
{
    protected RuleRegistry $registry;

    public function __construct(
        protected ?Translator $translator = null,
    ) {
        $this->registry = new RuleRegistry();
        $this->registry->registerMany(self::defaultRules());
    }

    public function registry(): RuleRegistry
    {
        return $this->registry;
    }

    /**
     * Register an extra rule, or override a built-in.
     */
    public function addRule(string $name, Rule $rule): self
    {
        $this->registry->register($name, $rule);

        return $this;
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, mixed>  $rules
     */
    public function make(array $data, array $rules): Validator
    {
        $translator = $this->translator ?? new GettextTranslator();

        $validator = new Validator(
            $data,
            new RuleParser($this->registry),
            new MessageResolver($translator),
        );

        return $validator->setRules($rules);
    }

    /**
     * Every rule name the engine understands.
     *
     * A few names deliberately share a class: `regex`/`matches` and
     * `integer`/`number` are aliases, and `default`/`defaults` are the same
     * rule spelled two ways. They are separate registry entries rather than
     * lookup aliases so that each keeps the name the form author wrote,
     * which is what per-field custom messages are keyed on.
     *
     * @return array<string, Rule>
     */
    public static function defaultRules(): array
    {
        return [
            // Presence and conditional presence
            'required'                 => new Rules\RequiredRule(),
            'required_if'              => new Rules\RequiredIfRule(),
            'required_unless'          => new Rules\RequiredUnlessRule(),
            'required_with'            => new Rules\RequiredWithRule(),
            'required_with_all'        => new Rules\RequiredWithAllRule(),
            'required_without'         => new Rules\RequiredWithoutRule(),
            'required_without_all'     => new Rules\RequiredWithoutAllRule(),
            'requires'                 => new Rules\RequiresRule(),
            'present'                  => new Rules\PresentRule(),
            'accepted'                 => new Rules\AcceptedRule(),
            'rejected'                 => new Rules\RejectedRule(),

            // Prohibition
            'prohibited'               => new Rules\ProhibitedRule(),
            'prohibited_if'            => new Rules\ProhibitedIfRule(),
            'prohibited_unless'        => new Rules\ProhibitedUnlessRule(),
            'prohibited_with'          => new Rules\ProhibitedWithRule(),
            'prohibited_with_all'      => new Rules\ProhibitedWithAllRule(),
            'prohibited_without'       => new Rules\ProhibitedWithoutRule(),
            'prohibited_without_all'   => new Rules\ProhibitedWithoutAllRule(),

            // Flow control
            'nullable'                 => new Rules\NullableRule(),
            'sometimes'                => new Rules\SometimesRule(),
            'default'                  => new Rules\DefaultsRule(),
            'defaults'                 => new Rules\DefaultsRule(),
            'callback'                 => new Rules\CallbackRule(),

            // Types
            'array'                    => new Rules\TypeArrayRule(),
            'boolean'                  => new Rules\TypeBooleanRule(),
            'string'                   => new Rules\TypeStringRule(),
            'numeric'                  => new Rules\NumericRule(),
            'integer'                  => new Rules\TypeIntegerRule(),
            'number'                   => new Rules\TypeIntegerRule(),
            'float'                    => new Rules\TypeFloatRule(),

            // Size
            'max'                      => new Rules\MaxRule(),
            'min'                      => new Rules\MinRule(),
            'between'                  => new Rules\BetweenRule(),
            'length'                   => new Rules\LengthRule(),
            'digits'                   => new Rules\DigitsRule(),
            'digits_between'           => new Rules\DigitsBetweenRule(),

            // Sets and comparisons
            'in'                       => new Rules\InRule(),
            'not_in'                   => new Rules\NotInRule(),
            'any_of'                   => new Rules\AnyOfRule(),
            'same'                     => new Rules\SameRule(),
            'different'                => new Rules\DifferentRule(),
            'array_must_have_keys'     => new Rules\ArrayMustHaveKeysRule(),
            'array_can_only_have_keys' => new Rules\ArrayCanOnlyHaveKeysRule(),

            // Strings and formats
            'alpha'                    => new Rules\AlphaRule(),
            'alpha_num'                => new Rules\AlphaNumRule(),
            'alpha_dash'               => new Rules\AlphaDashRule(),
            'alpha_spaces'             => new Rules\AlphaSpacesRule(),
            'lowercase'                => new Rules\LowercaseRule(),
            'uppercase'                => new Rules\UppercaseRule(),
            'starts_with'              => new Rules\StartsWithRule(),
            'ends_with'                => new Rules\EndsWithRule(),
            'regex'                    => new Rules\RegexRule(),
            'matches'                  => new Rules\RegexRule(),
            'email'                    => new Rules\EmailRule(),
            'url'                      => new Rules\UrlRule(),
            'json'                     => new Rules\JsonRule(),
            'ip'                       => new Rules\IpRule(),
            'ipv4'                     => new Rules\Ipv4Rule(),
            'ipv6'                     => new Rules\Ipv6Rule(),
            'uuid'                     => new Rules\UuidRule(),
            'phone'                    => new Rules\PhoneNumberRule(),

            // Dates
            'date'                     => new Rules\DateRule(),
            'after'                    => new Rules\AfterRule(),
            'before'                   => new Rules\BeforeRule(),

            // Files. The last three are TOFU's own, added because the
            // generic ones cannot express "a file survived the confirm step"
            // or check content rather than filename.
            'uploaded_file'            => new Rules\UploadedFileRule(),
            'mimes'                    => new Rules\MimesRule(),
            'extension'                => new Rules\ExtensionRule(),
            'custom_required_file'     => new Rules\RequiredFileRule(),
            'max_mb'                   => new Rules\MaxMbRule(),
            'mime_type'                => new Rules\MimeTypeRule(),
        ];
    }
}
