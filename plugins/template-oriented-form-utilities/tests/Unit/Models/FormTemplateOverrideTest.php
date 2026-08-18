<?php

namespace TofuPlugin\Tests\Unit\Models;

use TofuPlugin\Models\Form;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;
use TofuPlugin\Structure\TemplateConfig;
use TofuPlugin\Structure\ValidationConfig;
use TofuPlugin\Tests\Unit\BaseTestCase;

class FormTemplateOverrideTest extends BaseTestCase
{
    private function makeForm(?TemplateConfig $template): Form
    {
        $config = new FormConfig(
            key:        'contact',
            name:       'Contact Form',
            template:   $template,
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
                allows: ['name'],
                rules:  ['name' => 'required'],
                names:  ['name' => 'Name'],
            ),
        );

        return new Form($config);
    }

    /**
     * Invoke the protected Form::getTemplate() via reflection — it is the
     * single choke point redirect() reads from, so asserting on it verifies
     * the override resolution logic without needing to exercise
     * wp_safe_redirect()/exit in redirect() itself.
     */
    private function resolvedTemplate(Form $form): ?TemplateConfig
    {
        $method = new \ReflectionMethod(Form::class, 'getTemplate');
        $method->setAccessible(true);
        return $method->invoke($form);
    }

    public function testGetTemplateReturnsStaticConfigWhenNoOverrideIsSet(): void
    {
        $static = new TemplateConfig(
            inputPath:   '/contact/',
            resultPath:  '/contact/result/',
            confirmPath: '/contact/confirm/',
        );
        $form = $this->makeForm($static);

        $this->assertSame($static, $this->resolvedTemplate($form));
    }

    public function testSetTemplateOverridesStaticConfigForRootRelativePaths(): void
    {
        $static = new TemplateConfig(
            inputPath:  '/contact/',
            resultPath: '/contact/result/',
        );
        $form = $this->makeForm($static);

        $override = new TemplateConfig(
            inputPath:   '/news/hello-world/',
            resultPath:  '/news/hello-world/thanks/',
            confirmPath: '/news/hello-world/confirm/',
        );
        $form->setTemplate($override);

        $resolved = $this->resolvedTemplate($form);
        $this->assertSame($override, $resolved);
        $this->assertSame('/news/hello-world/', $resolved->inputPath);
        $this->assertSame('/news/hello-world/confirm/', $resolved->confirmPath);
        $this->assertSame('/news/hello-world/thanks/', $resolved->resultPath);
    }

    public function testSetTemplateAcceptsSameHostAbsoluteUrls(): void
    {
        $form = $this->makeForm(new TemplateConfig(
            inputPath:  '/contact/',
            resultPath: '/contact/result/',
        ));

        $override = new TemplateConfig(
            inputPath:  'http://example.com/news/hello-world/',
            resultPath: 'http://example.com/news/hello-world/thanks/',
        );
        $form->setTemplate($override);

        $this->assertSame($override, $this->resolvedTemplate($form));
    }

    public function testSetTemplateRejectsCrossHostUrlAndKeepsStaticConfig(): void
    {
        $static = new TemplateConfig(
            inputPath:  '/contact/',
            resultPath: '/contact/result/',
        );
        $form = $this->makeForm($static);

        $override = new TemplateConfig(
            inputPath:  '/news/hello-world/',
            resultPath: 'https://evil.example.com/thanks/',
        );
        $form->setTemplate($override);

        // The whole override is rejected — not just the offending path —
        // so the visitor keeps a consistent, safe redirect target.
        $this->assertSame($static, $this->resolvedTemplate($form));
    }

    public function testFormWithoutSetTemplateCallBehavesUnchanged(): void
    {
        $static = new TemplateConfig(
            inputPath:  '/contact/',
            resultPath: '/contact/result/',
        );
        $form = $this->makeForm($static);

        $this->assertSame($static, $this->resolvedTemplate($form));
    }
}
