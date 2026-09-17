<?php

namespace TofuPlugin\Tests\Unit\Models;

use TofuPlugin\Consts;
use TofuPlugin\Models\Form;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;
use TofuPlugin\Structure\TemplateConfig;
use TofuPlugin\Structure\ValidationConfig;
use TofuPlugin\Tests\Unit\BaseTestCase;

/**
 * Covers the hidden-field mechanism that carries a Form::setTemplate()
 * override across the input POST without setTemplate() itself ever writing
 * to the session — see setTemplate()'s docblock in src/Models/Form.php.
 *
 * The behaviour this guards against: a theme calling setTemplate() while
 * rendering a page must not, by itself, cause a Set-Cookie on that GET
 * response, since that would make the page uncacheable by any full-page
 * cache regardless of whether the visitor ever submits the form.
 */
class FormTemplateOverrideHiddenFieldTest extends BaseTestCase
{
    /**
     * $staticTemplate deliberately omits confirmPath in most callers, to
     * mirror a form that relies entirely on the per-request override —
     * dynamicTemplate: true is what makes that legal (see FormConfig's
     * constructor guard).
     */
    private function makeForm(?TemplateConfig $staticTemplate = null): Form
    {
        $config = new FormConfig(
            key:             'contact',
            name:            'Contact Form',
            template:        $staticTemplate ?? new TemplateConfig(
                inputPath:  '/contact/',
                resultPath: '/contact/result/',
            ),
            mail:            new MailConfig(
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
            validation:      new ValidationConfig(
                allows: ['name'],
                rules:  ['name' => 'required'],
                names:  ['name' => 'Name'],
            ),
            confirmStep:     true,
            dynamicTemplate: true,
        );

        return new Form($config);
    }

    private function resolvedTemplate(Form $form): ?TemplateConfig
    {
        $method = new \ReflectionMethod(Form::class, 'getTemplate');
        $method->setAccessible(true);
        return $method->invoke($form);
    }

    private function templateOverrideHidden(Form $form): string
    {
        $method = new \ReflectionMethod(Form::class, 'templateOverrideHidden');
        $method->setAccessible(true);
        return $method->invoke($form);
    }

    // -----------------------------------------------------------------
    // setTemplate() itself never writes to the session
    // -----------------------------------------------------------------

    public function testSetTemplateAloneIssuesNoCookieAndWritesNoSession(): void
    {
        $form = $this->makeForm();

        $form->setTemplate(new TemplateConfig(
            inputPath:  '/news/hello-world/',
            resultPath: '/news/hello-world/thanks/',
        ));

        $this->assertSame(
            [],
            $GLOBALS['__tofu_setcookie_calls'],
            'setTemplate() must not issue a session cookie by itself'
        );
    }

    /**
     * Reproduces the reported real-world scenario: a theme registers several
     * forms and calls setTemplate() for all of them on every page — including
     * pages that show none of them — because a shared include runs site-wide.
     * None of that may write a cookie; only an actual submission should.
     */
    public function testCallingSetTemplateForSeveralFormsOnOnePageViewIssuesNoCookie(): void
    {
        $forms = [
            $this->makeForm(),
            $this->makeForm(),
            $this->makeForm(),
            $this->makeForm(),
        ];

        foreach ($forms as $i => $form) {
            $form->setTemplate(new TemplateConfig(
                inputPath:  "/page-{$i}/",
                resultPath: "/page-{$i}/thanks/",
            ));
        }

        $this->assertSame([], $GLOBALS['__tofu_setcookie_calls']);
    }

    // -----------------------------------------------------------------
    // templateOverrideHidden()
    // -----------------------------------------------------------------

    public function testTemplateOverrideHiddenIsEmptyWithNoOverride(): void
    {
        $form = $this->makeForm();

        $this->assertSame('', $this->templateOverrideHidden($form));
    }

    public function testTemplateOverrideHiddenEncodesTheOverride(): void
    {
        $form = $this->makeForm();
        $form->setTemplate(new TemplateConfig(
            inputPath:   '/news/hello-world/',
            resultPath:  '/news/hello-world/thanks/',
            confirmPath: '/news/hello-world/confirm/',
        ));

        $html = $this->templateOverrideHidden($form);

        $this->assertNotSame('', $html);
        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString(Consts::TEMPLATE_OVERRIDE_INPUT_NAME, $html);

        preg_match('/value="([^"]+)"/', $html, $matches);
        $decoded = json_decode(base64_decode($matches[1]), true);

        $this->assertSame([
            'inputPath'   => '/news/hello-world/',
            'resultPath'  => '/news/hello-world/thanks/',
            'confirmPath' => '/news/hello-world/confirm/',
        ], $decoded);
    }

    // -----------------------------------------------------------------
    // Round trip through processInput()
    // -----------------------------------------------------------------

    public function testProcessInputAppliesTheHiddenFieldAndPersistsIt(): void
    {
        $renderForm = $this->makeForm();
        $renderForm->setTemplate(new TemplateConfig(
            inputPath:   '/news/hello-world/',
            resultPath:  '/news/hello-world/thanks/',
            confirmPath: '/news/hello-world/confirm/',
        ));
        $hidden = $this->templateOverrideHidden($renderForm);
        preg_match('/value="([^"]+)"/', $hidden, $matches);
        $encoded = $matches[1];

        // A separate Form instance stands in for the fresh request the POST
        // actually arrives on — its constructor has restored nothing from
        // the session, since setTemplate() never wrote to it above.
        $submitForm = $this->makeForm();
        $result = $submitForm->processInput([
            'name' => 'Taro',
            Consts::TEMPLATE_OVERRIDE_INPUT_NAME => $encoded,
        ], []);

        $this->assertSame('confirm', $result['next']);
        $resolved = $this->resolvedTemplate($submitForm);
        $this->assertSame('/news/hello-world/', $resolved->inputPath);
        $this->assertSame('/news/hello-world/confirm/', $resolved->confirmPath);
        $this->assertSame('/news/hello-world/thanks/', $resolved->resultPath);

        // processInput() still calls storeSession() unconditionally, exactly
        // as before this change — the POST itself is expected to issue the
        // cookie; only the earlier GET must no longer do so.
        $this->assertNotSame([], $GLOBALS['__tofu_setcookie_calls']);
    }

    public function testTamperedCrossHostHiddenFieldIsIgnored(): void
    {
        $static = new TemplateConfig(
            inputPath:  '/contact/',
            resultPath: '/contact/result/',
        );
        $form = $this->makeForm($static);

        $encoded = base64_encode(json_encode([
            'inputPath'  => '/news/hello-world/',
            'resultPath' => 'https://evil.example.com/thanks/',
        ]));

        $result = $form->processInput([
            'name' => 'Taro',
            Consts::TEMPLATE_OVERRIDE_INPUT_NAME => $encoded,
        ], []);

        $this->assertSame($static, $this->resolvedTemplate($form));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function malformedHiddenFieldValues(): array
    {
        return [
            'not base64/json at all' => ['not-valid-at-all'],
            'valid base64, not json' => [base64_encode('not json')],
            'json but not an object' => [base64_encode(json_encode(['just', 'an', 'array']))],
            'missing resultPath'     => [base64_encode(json_encode(['inputPath' => '/a/']))],
            'inputPath not a string' => [base64_encode(json_encode(['inputPath' => 1, 'resultPath' => '/a/']))],
        ];
    }

    /**
     * @dataProvider malformedHiddenFieldValues
     */
    public function testMalformedHiddenFieldIsIgnoredWithoutError(mixed $value): void
    {
        $static = new TemplateConfig(
            inputPath:  '/contact/',
            resultPath: '/contact/result/',
        );
        $form = $this->makeForm($static);

        $result = $form->processInput([
            'name' => 'Taro',
            Consts::TEMPLATE_OVERRIDE_INPUT_NAME => $value,
        ], []);

        $this->assertSame($static, $this->resolvedTemplate($form));
    }

    public function testOrdinaryFormWithNoDynamicTemplateIsUnaffected(): void
    {
        $static = new TemplateConfig(
            inputPath:  '/contact/',
            resultPath: '/contact/result/',
        );
        $form = $this->makeForm($static);

        // No hidden field at all in $post — the common case.
        $result = $form->processInput(['name' => 'Taro'], []);

        $this->assertSame('confirm', $result['next']);
        $this->assertSame($static, $this->resolvedTemplate($form));
    }
}
