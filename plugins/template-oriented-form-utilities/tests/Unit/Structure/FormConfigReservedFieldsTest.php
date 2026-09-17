<?php

namespace TofuPlugin\Tests\Unit\Structure;

use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;
use TofuPlugin\Structure\TemplateConfig;
use TofuPlugin\Structure\ValidationConfig;
use TofuPlugin\Tests\Unit\BaseTestCase;

/**
 * A form declaring a field under the plugin's own prefix would collide with
 * an input formClose() renders. PHP keeps only the last value for a repeated
 * name and the plugin's input comes last, so the form's own value would be
 * dropped with no error anywhere — these tests pin that it is refused at
 * registration instead.
 */
class FormConfigReservedFieldsTest extends BaseTestCase
{
    private function makeConfig(ValidationConfig $validation): FormConfig
    {
        return new FormConfig(
            key:        'contact',
            name:       'Contact Form',
            template:   new TemplateConfig(
                inputPath:  '/contact/',
                resultPath: '/contact/result/',
            ),
            mail:       new MailConfig(
                fromEmail:  'noreply@example.com',
                fromName:   'Test',
                recipients: new MailRecipientsCollection([
                    new MailRecipientsConfig(
                        recipientEmail: 'admin@example.com',
                        subject:        'Test',
                        mailBody:       'Test body',
                    ),
                ]),
            ),
            validation: $validation,
        );
    }

    public function testOrdinaryFieldNamesAreAccepted(): void
    {
        $config = $this->makeConfig(new ValidationConfig(
            allows: ['name', 'email', 'message'],
            rules:  ['name' => 'required'],
            names:  ['name' => 'Name'],
        ));

        $this->assertSame('contact', $config->key);
    }

    /**
     * Both prefixes are refused: the plugin's hidden inputs use '_tofu_' and
     * '__tofu_', so neither is ever a legitimate name for a form's own field.
     *
     * @return array<string, array{string}>
     */
    public static function reservedNames(): array
    {
        return [
            'single underscore'           => ['_tofu_something'],
            'double underscore'           => ['__tofu_something'],
            'the nonce field itself'      => ['__tofu_contact_nonce'],
            'the uploaded-files field'    => ['__tofu_uploaded_files'],
            'the template-override field' => ['__tofu_template_override'],
            'the reCAPTCHA token field'   => ['__tofu_recaptcha_token'],
            'the Turnstile token field'   => ['__tofu_turnstile_token'],
            // The spellings these field names used before they moved to the
            // `__tofu_` prefix stay reserved, so a form cannot claim one.
            'a legacy field name'         => ['_tofu_recaptcha_token'],
        ];
    }

    /**
     * @dataProvider reservedNames
     */
    public function testReservedNameInAllowsThrows(string $field): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($field);

        $this->makeConfig(new ValidationConfig(
            allows: ['name', $field],
            rules:  ['name' => 'required'],
            names:  ['name' => 'Name'],
        ));
    }

    public function testReservedNameInRulesThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeConfig(new ValidationConfig(
            allows: ['name'],
            rules:  ['name' => 'required', '_tofu_sneaky' => 'required'],
            names:  ['name' => 'Name'],
        ));
    }

    public function testReservedNameInNamesThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeConfig(new ValidationConfig(
            allows: ['name'],
            rules:  ['name' => 'required'],
            names:  ['name' => 'Name', '__tofu_sneaky' => 'Sneaky'],
        ));
    }

    public function testReservedNameInMessagesThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeConfig(new ValidationConfig(
            allows:   ['name'],
            rules:    ['name' => 'required'],
            names:    ['name' => 'Name'],
            messages: ['_tofu_sneaky' => ['required' => 'nope']],
        ));
    }

    public function testReservedNameInRecordsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeConfig(new ValidationConfig(
            allows:  ['name'],
            rules:   ['name' => 'required'],
            names:   ['name' => 'Name'],
            records: ['name', '__tofu_sneaky'],
        ));
    }

    public function testTheMessageNamesTheOffendingFieldAndTheForm(): void
    {
        try {
            $this->makeConfig(new ValidationConfig(
                allows: ['name', '_tofu_oops'],
                rules:  ['name' => 'required'],
                names:  ['name' => 'Name'],
            ));
            $this->fail('Expected an InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('_tofu_oops', $e->getMessage());
            $this->assertStringContainsString('contact', $e->getMessage());
        }
    }

    /**
     * A name that merely mentions tofu, without the prefix, is fine — the
     * guard must not get in the way of ordinary field names.
     */
    public function testNamesThatOnlyResembleThePrefixAreAccepted(): void
    {
        $config = $this->makeConfig(new ValidationConfig(
            allows: ['tofu_order', 'my_tofu_field', 'tofu'],
            rules:  ['tofu_order' => 'required'],
            names:  ['tofu_order' => 'Order'],
        ));

        $this->assertSame('contact', $config->key);
    }
}
