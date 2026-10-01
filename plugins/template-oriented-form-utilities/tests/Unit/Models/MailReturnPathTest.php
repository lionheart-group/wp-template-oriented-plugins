<?php

namespace TofuPlugin\Tests\Unit\Models;

use TofuPlugin\Models\FieldValueCollection;
use TofuPlugin\Models\Form;
use TofuPlugin\Models\Mail;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;
use TofuPlugin\Structure\TemplateConfig;
use TofuPlugin\Structure\ValidationConfig;
use TofuPlugin\Tests\Unit\BaseTestCase;

/**
 * Covers MailConfig::$returnPath / Mail::setReturnPath().
 *
 * WordPress ignores a "Return-Path:" header, so the envelope sender is set on
 * PHPMailer's Sender via phpmailer_init. The wp_mail() stub fires that action
 * on a stand-in object and records the resulting Sender as 'sender'.
 */
class MailReturnPathTest extends BaseTestCase
{
    private function makeMailConfig(?string $returnPath = null): MailConfig
    {
        return new MailConfig(
            fromEmail:  'noreply@example.com',
            fromName:   'Test',
            recipients: new MailRecipientsCollection([
                new MailRecipientsConfig(
                    recipientEmail: 'admin@example.com',
                    subject:        'Subject for {name}',
                    mailBody:       'Body for {name}',
                ),
            ]),
            returnPath: $returnPath,
        );
    }

    private function makeForm(?string $returnPath = null): Form
    {
        $form = new Form(new FormConfig(
            key:        'contact',
            name:       'Contact Form',
            template:   new TemplateConfig(
                inputPath:  '/contact/',
                resultPath: '/contact/result/',
            ),
            mail:       $this->makeMailConfig($returnPath),
            validation: new ValidationConfig(
                allows: ['name'],
                rules:  ['name' => 'required'],
                names:  ['name' => 'Name'],
            ),
        ));

        $values = new FieldValueCollection();
        $values->addValue('name', 'Taro');

        $property = new \ReflectionProperty(Form::class, 'values');
        $property->setAccessible(true);
        $property->setValue($form, $values);

        return $form;
    }

    public function testReturnPathDefaultsToNull(): void
    {
        $this->assertNull($this->makeMailConfig()->returnPath);
    }

    public function testInvalidReturnPathThrowsAtRegistration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->makeMailConfig('not-an-email');
    }

    public function testConfiguredReturnPathBecomesTheEnvelopeSender(): void
    {
        $result = $this->makeForm('bounce@example.com')->processConfirm(skipVerify: true);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $GLOBALS['__tofu_wp_mail_calls']);
        $this->assertSame('bounce@example.com', $GLOBALS['__tofu_wp_mail_calls'][0]['sender']);
    }

    public function testNoReturnPathLeavesTheSenderUntouched(): void
    {
        $this->makeForm()->processConfirm(skipVerify: true);

        $this->assertNull($GLOBALS['__tofu_wp_mail_calls'][0]['sender']);
        $this->assertArrayNotHasKey('phpmailer_init', $GLOBALS['__tofu_hooks']);
    }

    public function testSenderDoesNotLeakIntoLaterMail(): void
    {
        (new Mail())
            ->addTo('admin@example.com')
            ->setReturnPath('bounce@example.com')
            ->send();

        $this->assertArrayNotHasKey('phpmailer_init', $GLOBALS['__tofu_hooks'], 'the callback is removed after send()');

        (new Mail())->addTo('admin@example.com')->send();

        $this->assertSame('bounce@example.com', $GLOBALS['__tofu_wp_mail_calls'][0]['sender']);
        $this->assertNull($GLOBALS['__tofu_wp_mail_calls'][1]['sender']);
    }

    public function testPreSendMailHookCanOverrideTheReturnPath(): void
    {
        add_action('tofu_pre_send_mail', function (Mail $mail) {
            $mail->setReturnPath('override@example.com');
        });

        $this->makeForm('bounce@example.com')->processConfirm(skipVerify: true);

        $this->assertSame('override@example.com', $GLOBALS['__tofu_wp_mail_calls'][0]['sender']);
    }
}
