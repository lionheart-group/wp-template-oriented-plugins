<?php

namespace TofuPlugin\Tests\Unit\Structure;

use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;
use TofuPlugin\Structure\TemplateConfig;
use TofuPlugin\Structure\ValidationConfig;
use TofuPlugin\Tests\Unit\BaseTestCase;

class FormConfigAjaxTest extends BaseTestCase
{
    private function makeMinimalConfig(array $overrides = []): FormConfig
    {
        return new FormConfig(
            key:        $overrides['key'] ?? 'test-form',
            name:       'Test Form',
            template:   new TemplateConfig(
                inputPath:  '/form/',
                resultPath: '/form/result/',
            ),
            mail: new MailConfig(
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
                allows:  ['name'],
                rules:   ['name' => 'required'],
                filters: ['name' => 'trim'],
                names:   ['name' => 'Name'],
            ),
            ajaxEnabled: $overrides['ajaxEnabled'] ?? false,
            corsOrigins: $overrides['corsOrigins'] ?? [],
        );
    }

    public function testAjaxDisabledByDefault(): void
    {
        $config = $this->makeMinimalConfig();
        $this->assertFalse($config->ajaxEnabled);
    }

    public function testAjaxCanBeEnabled(): void
    {
        $config = $this->makeMinimalConfig(['ajaxEnabled' => true]);
        $this->assertTrue($config->ajaxEnabled);
    }

    public function testCorsOriginsEmptyByDefault(): void
    {
        $config = $this->makeMinimalConfig();
        $this->assertSame([], $config->corsOrigins);
    }

    public function testCorsOriginsCanBeSet(): void
    {
        $origins = ['https://frontend.example.com', 'https://app.example.com'];
        $config = $this->makeMinimalConfig(['corsOrigins' => $origins]);
        $this->assertSame($origins, $config->corsOrigins);
    }

    public function testCorsOriginsAreSameOriginOnlyWhenEmpty(): void
    {
        $config = $this->makeMinimalConfig(['ajaxEnabled' => true, 'corsOrigins' => []]);
        $this->assertTrue($config->ajaxEnabled);
        $this->assertEmpty($config->corsOrigins);
    }
}
