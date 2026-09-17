<?php

namespace TofuPlugin\Tests\Unit\Models;

use TofuPlugin\Models\Form;
use TofuPlugin\Models\Validation;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;
use TofuPlugin\Structure\TemplateConfig;
use TofuPlugin\Structure\ValidationConfig;
use TofuPlugin\Tests\Unit\BaseTestCase;
use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\ValidatorFactory;

class ValidationHooksTest extends BaseTestCase
{
    /**
     * @param array<string, mixed> $rules
     */
    private function makeForm(array $rules): Form
    {
        $config = new FormConfig(
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
            validation: new ValidationConfig(
                allows: array_keys($rules),
                rules:  $rules,
                names:  ['code' => 'Code'],
            ),
        );

        return new Form($config);
    }

    public function testRegisterValidationRulesReceivesTheFactoryAndConfig(): void
    {
        $received = [];
        add_action('tofu_register_validation_rules', function ($factory, $config) use (&$received) {
            $received = ['factory' => $factory, 'config' => $config];
        }, 10, 2);

        $form = $this->makeForm(['code' => 'required']);
        (new Validation())->validate($form, ['code' => 'abc']);

        $this->assertInstanceOf(ValidatorFactory::class, $received['factory'] ?? null);
        $this->assertInstanceOf(FormConfig::class, $received['config'] ?? null);
        $this->assertSame('contact', $received['config']->key);
    }

    public function testACustomRuleRegisteredViaTheHookCanBeUsedByName(): void
    {
        add_action('tofu_register_validation_rules', function ($factory) {
            $factory->addRule('tofu_test_even', new class extends Rule {
                public function check($value): bool
                {
                    return is_numeric($value) && (int) $value % 2 === 0;
                }
            });
        });

        // Without the hook this rule name would throw at parse time.
        $form = $this->makeForm(['code' => 'required|tofu_test_even']);

        (new Validation())->validate($form, ['code' => '4']);
        $this->assertFalse($form->getErrors()->hasErrors(), 'an even value passes');

        $form = $this->makeForm(['code' => 'required|tofu_test_even']);
        (new Validation())->validate($form, ['code' => '3']);
        $this->assertTrue($form->getErrors()->hasErrors(), 'an odd value fails');
    }

    public function testUnregisteredRuleStillThrows(): void
    {
        // Guards the premise of the test above: the rule name is only known
        // because the hook registered it, not because it exists by default.
        $form = $this->makeForm(['code' => 'required|tofu_test_even']);

        $this->expectException(\InvalidArgumentException::class);
        (new Validation())->validate($form, ['code' => '4']);
    }
}
