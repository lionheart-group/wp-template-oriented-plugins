<?php

namespace TofuPlugin\Tests\Unit\Helpers;

use TofuPlugin\Helpers\Form as FormHelper;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;
use TofuPlugin\Structure\TemplateConfig;
use TofuPlugin\Structure\ValidationConfig;
use TofuPlugin\Tests\Unit\BaseTestCase;

class FormHelperTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset the static forms registry before each test via reflection
        $ref = new \ReflectionProperty(FormHelper::class, 'forms');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    private function makeConfig(string $key, bool $ajaxEnabled = false): FormConfig
    {
        return new FormConfig(
            key:        $key,
            name:       ucfirst($key) . ' Form',
            template:   new TemplateConfig(
                inputPath:  '/' . $key . '/',
                resultPath: '/' . $key . '/result/',
            ),
            mail: new MailConfig(
                fromEmail:  'noreply@example.com',
                fromName:   'Test',
                recipients: new MailRecipientsCollection([
                    new MailRecipientsConfig(
                        recipientEmail: 'admin@example.com',
                        subject:        'Test',
                        mailBody:       'Body',
                    ),
                ]),
            ),
            validation: new ValidationConfig(
                allows:  ['name'],
                rules:   ['name' => 'required'],
                names:   ['name' => 'Name'],
            ),
            ajaxEnabled: $ajaxEnabled,
        );
    }

    public function testGetAllReturnsEmptyArrayWhenNoFormsRegistered(): void
    {
        $this->assertSame([], FormHelper::getAll());
    }

    public function testGetAllReturnsRegisteredForms(): void
    {
        FormHelper::register($this->makeConfig('contact'));
        FormHelper::register($this->makeConfig('newsletter'));

        $all = FormHelper::getAll();
        $this->assertCount(2, $all);
        $this->assertSame('contact',    $all[0]->getKey());
        $this->assertSame('newsletter', $all[1]->getKey());
    }

    public function testGetReturnsFalseForUnknownKeyInNonStrictMode(): void
    {
        $result = FormHelper::get('nonexistent', strict: false);
        $this->assertFalse($result);
    }

    public function testGetThrowsForUnknownKeyInStrictMode(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/nonexistent/');
        FormHelper::get('nonexistent', strict: true);
    }

    public function testGetReturnsFormByKey(): void
    {
        FormHelper::register($this->makeConfig('contact'));
        $form = FormHelper::get('contact', strict: false);
        $this->assertNotFalse($form);
        $this->assertSame('contact', $form->getKey());
    }

    public function testGetAllFiltersAjaxEnabledForms(): void
    {
        FormHelper::register($this->makeConfig('contact',    ajaxEnabled: false));
        FormHelper::register($this->makeConfig('newsletter', ajaxEnabled: true));

        $ajaxForms = array_filter(
            FormHelper::getAll(),
            fn ($f) => $f->config->ajaxEnabled
        );

        $this->assertCount(1, $ajaxForms);
        $this->assertSame('newsletter', array_values($ajaxForms)[0]->getKey());
    }

    public function testRegisterDuplicateKeyThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        FormHelper::register($this->makeConfig('contact'));
        FormHelper::register($this->makeConfig('contact'));
    }
}
