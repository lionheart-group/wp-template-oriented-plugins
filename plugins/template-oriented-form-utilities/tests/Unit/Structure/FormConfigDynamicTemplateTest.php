<?php

namespace TofuPlugin\Tests\Unit\Structure;

use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;
use TofuPlugin\Structure\TemplateConfig;
use TofuPlugin\Structure\ValidationConfig;
use TofuPlugin\Tests\Unit\BaseTestCase;

class FormConfigDynamicTemplateTest extends BaseTestCase
{
    private function makeMailConfig(): MailConfig
    {
        return new MailConfig(
            fromEmail:  'noreply@example.com',
            fromName:   'Test',
            recipients: new MailRecipientsCollection([
                new MailRecipientsConfig(
                    recipientEmail: 'admin@example.com',
                    subject:        'Test',
                    mailBody:       'Test body',
                ),
            ]),
        );
    }

    private function makeValidationConfig(): ValidationConfig
    {
        return new ValidationConfig(
            allows: ['name'],
            rules:  ['name' => 'required'],
            names:  ['name' => 'Name'],
        );
    }

    public function testConfirmStepWithoutConfirmPathAndWithoutDynamicTemplateThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/confirmStep is true but template->confirmPath is not set/');

        new FormConfig(
            key:         'contact',
            name:        'Contact Form',
            template:    new TemplateConfig(
                inputPath:  '/contact/',
                resultPath: '/contact/result/',
            ),
            mail:        $this->makeMailConfig(),
            validation:  $this->makeValidationConfig(),
            confirmStep: true,
        );
    }

    public function testConfirmStepWithDynamicTemplateAndNoStaticConfirmPathDoesNotThrow(): void
    {
        $config = new FormConfig(
            key:             'contact',
            name:            'Contact Form',
            template:        new TemplateConfig(
                inputPath:  '/contact/',
                resultPath: '/contact/result/',
            ),
            mail:            $this->makeMailConfig(),
            validation:      $this->makeValidationConfig(),
            confirmStep:     true,
            dynamicTemplate: true,
        );

        $this->assertTrue($config->confirmStep);
        $this->assertTrue($config->dynamicTemplate);
        $this->assertNull($config->template->confirmPath);
    }

    public function testConfirmStepWithDynamicTemplateAndNoTemplateAtAllDoesNotThrow(): void
    {
        $config = new FormConfig(
            key:             'contact',
            name:            'Contact Form',
            mail:            $this->makeMailConfig(),
            validation:      $this->makeValidationConfig(),
            confirmStep:     true,
            dynamicTemplate: true,
        );

        $this->assertNull($config->template);
    }

    public function testDynamicTemplateFalseByDefault(): void
    {
        $config = new FormConfig(
            key:        'contact',
            name:       'Contact Form',
            template:   new TemplateConfig(
                inputPath:   '/contact/',
                resultPath:  '/contact/result/',
                confirmPath: '/contact/confirm/',
            ),
            mail:        $this->makeMailConfig(),
            validation:  $this->makeValidationConfig(),
            confirmStep: true,
        );

        $this->assertFalse($config->dynamicTemplate);
    }
}
