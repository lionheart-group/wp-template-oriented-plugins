<?php

namespace TofuPlugin\Tests\Unit\Structure;

use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;
use TofuPlugin\Structure\TemplateConfig;
use TofuPlugin\Structure\ValidationConfig;
use TofuPlugin\Tests\Unit\BaseTestCase;

class FormConfigRecordTest extends BaseTestCase
{
    private function makeConfig(array $overrides = []): FormConfig
    {
        return new FormConfig(
            key:  $overrides['key'] ?? 'test-form',
            name: 'Test Form',
            template: new TemplateConfig(
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
                allows:  ['name', 'email', 'message'],
                rules:   ['name' => 'required', 'email' => 'required|email'],
                names:   ['name' => 'Name', 'email' => 'Email'],
                records: $overrides['records'] ?? [],
            ),
            saveToDatabase: $overrides['saveToDatabase'] ?? false,
        );
    }

    public function testSaveToDatabaseDefaultsFalse(): void
    {
        $config = $this->makeConfig();
        $this->assertFalse($config->saveToDatabase);
    }

    public function testSaveToDatabaseCanBeEnabled(): void
    {
        $config = $this->makeConfig(['saveToDatabase' => true]);
        $this->assertTrue($config->saveToDatabase);
    }

    public function testRecordsDefaultsToEmptyArray(): void
    {
        $config = $this->makeConfig();
        $this->assertSame([], $config->validation->records);
    }

    public function testRecordsCanBeSetToSubset(): void
    {
        $config = $this->makeConfig(['records' => ['name', 'email']]);
        $this->assertSame(['name', 'email'], $config->validation->records);
    }

    public function testSaveToDatabaseWithRecordsSubset(): void
    {
        $config = $this->makeConfig([
            'saveToDatabase' => true,
            'records'        => ['name', 'email'],
        ]);

        $this->assertTrue($config->saveToDatabase);
        $this->assertSame(['name', 'email'], $config->validation->records);
    }
}
