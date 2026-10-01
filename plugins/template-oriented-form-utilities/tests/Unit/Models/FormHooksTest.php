<?php

namespace TofuPlugin\Tests\Unit\Models;

use TofuPlugin\Models\FieldValueCollection;
use TofuPlugin\Models\Form;
use TofuPlugin\Models\UploadedFileCollection;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;
use TofuPlugin\Structure\TemplateConfig;
use TofuPlugin\Structure\ValidationConfig;
use TofuPlugin\Tests\Unit\BaseTestCase;

/**
 * Covers the hooks fired from Models\Form.
 *
 * Everything here drives processConfirm(skipVerify: true) — the method both the
 * redirect flow and the REST flow share — so a hook proven to fire here fires
 * for AJAX forms too.
 */
class FormHooksTest extends BaseTestCase
{
    /**
     * @param string[] $allows
     */
    private function makeForm(bool $saveToDatabase = false, array $allows = ['name']): Form
    {
        $config = new FormConfig(
            key:            'contact',
            name:           'Contact Form',
            template:       new TemplateConfig(
                inputPath:  '/contact/',
                resultPath: '/contact/result/',
            ),
            mail:           new MailConfig(
                fromEmail:  'noreply@example.com',
                fromName:   'Test',
                recipients: new MailRecipientsCollection([
                    new MailRecipientsConfig(
                        recipientEmail: 'admin@example.com',
                        subject:        'Subject for {name}',
                        mailBody:       'Body for {name}',
                    ),
                ]),
            ),
            validation:     new ValidationConfig(
                allows: $allows,
                rules:  ['name' => 'required'],
                names:  ['name' => 'Name'],
            ),
            saveToDatabase: $saveToDatabase,
        );

        $form = new Form($config);

        // processConfirm() reads the values the session would normally have
        // restored; there is no session in a unit test, so seed them directly.
        $values = new FieldValueCollection();
        $values->addValue('name', 'Taro');
        $values->addValue('secret', 'not-in-allows');

        $property = new \ReflectionProperty(Form::class, 'values');
        $property->setAccessible(true);
        $property->setValue($form, $values);

        return $form;
    }

    public function testFormSubmittedFiresOnceWithSubmittedValues(): void
    {
        $calls = [];
        add_action('tofu_form_submitted', function ($values, $files, $recordId, $config) use (&$calls) {
            $calls[] = compact('values', 'files', 'recordId', 'config');
        }, 10, 4);

        $form = $this->makeForm();
        $result = $form->processConfirm(skipVerify: true);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $calls);
        $this->assertSame('Taro', $calls[0]['values']['name']);
        $this->assertInstanceOf(UploadedFileCollection::class, $calls[0]['files']);
        $this->assertFalse($calls[0]['recordId'], 'recordId is false when saveToDatabase is off');
        $this->assertInstanceOf(FormConfig::class, $calls[0]['config']);
        $this->assertSame('contact', $calls[0]['config']->key);
    }

    public function testFormSubmittedDoesNotFireWhenTheSubmissionAlreadyCompleted(): void
    {
        $fired = 0;
        add_action('tofu_form_submitted', function () use (&$fired) {
            $fired++;
        });

        $form = $this->makeForm();

        // A resubmit arrives as a fresh request, so the guard sees the flushValue
        // the constructor restored from the session. Reproduce that state here.
        $flushValue = new \ReflectionProperty(Form::class, 'flushValue');
        $flushValue->setAccessible(true);
        $flushValue->setValue($form, 'already-completed');

        $result = $form->processConfirm(skipVerify: true);

        $this->assertTrue($result['success']);
        $this->assertSame('result', $result['next']);
        $this->assertSame(0, $fired, 'the flushValue guard must return before the hook');
        $this->assertCount(0, $GLOBALS['__tofu_wp_mail_calls'], 'and before any mail is re-sent');
    }

    public function testFormSubmittedReceivesRecordIdWhenSaveToDatabaseIsOn(): void
    {
        $recordId = null;
        add_action('tofu_form_submitted', function ($values, $files, $id) use (&$recordId) {
            $recordId = $id;
        }, 10, 3);

        $form = $this->makeForm(saveToDatabase: true);
        $form->processConfirm(skipVerify: true);

        $this->assertSame(1, $recordId, 'the $wpdb mock reports insert_id 1');
    }

    public function testNoHooksRegisteredLeavesBehaviourUnchanged(): void
    {
        $form = $this->makeForm();
        $result = $form->processConfirm(skipVerify: true);

        $this->assertTrue($result['success']);
        $this->assertSame('result', $result['next']);
        $this->assertCount(1, $GLOBALS['__tofu_wp_mail_calls']);
    }

    public function testPreSendMailFiresPerRecipientAndCanMutateTheMessage(): void
    {
        $recipients = [];
        add_action('tofu_pre_send_mail', function ($mail, $recipient, $values, $config) use (&$recipients) {
            $recipients[] = $recipient->recipientEmail;
            $mail->addHeader('X-Tofu-Test: 1');
        }, 10, 4);

        $form = $this->makeForm();
        $form->processConfirm(skipVerify: true);

        $this->assertSame(['admin@example.com'], $recipients);

        $sent = $GLOBALS['__tofu_wp_mail_calls'];
        $this->assertCount(1, $sent);
        $this->assertContains('X-Tofu-Test: 1', $sent[0]['headers']);
        // The hook runs after the message is built, so placeholders are resolved.
        $this->assertSame('Subject for Taro', $sent[0]['subject']);
    }

    public function testRecordValuesFilterRunsAfterTheAllowsIntersection(): void
    {
        $persisted = null;
        add_filter('tofu_record_values', function ($values) use (&$persisted) {
            $persisted = $values;
            return $values;
        });

        $form = $this->makeForm(saveToDatabase: true);
        $form->processConfirm(skipVerify: true);

        $this->assertIsArray($persisted);
        $this->assertArrayHasKey('name', $persisted);
        $this->assertArrayNotHasKey(
            'secret',
            $persisted,
            'the $allows mass-assignment guard must already have stripped this before the filter sees it'
        );
    }

    public function testRecordValuesFilterCanAddServerSideMetadata(): void
    {
        add_filter('tofu_record_values', function ($values) {
            $values['ip'] = '203.0.113.1';
            return $values;
        });

        $received = null;
        add_action('tofu_form_submitted', function () {
        });
        add_filter('tofu_record_values', function ($values) use (&$received) {
            $received = $values;
            return $values;
        }, 20);

        $form = $this->makeForm(saveToDatabase: true);
        $form->processConfirm(skipVerify: true);

        $this->assertSame('203.0.113.1', $received['ip'] ?? null);
    }

    public function testRecordValuesFilterIgnoresNonArrayReturn(): void
    {
        add_filter('tofu_record_values', fn () => 'not an array');

        $form = $this->makeForm(saveToDatabase: true);
        $result = $form->processConfirm(skipVerify: true);

        // A faulty callback must not take the submission down.
        $this->assertTrue($result['success']);
    }

    public function testValidationFailedFiresWithTheErrorsAndConfig(): void
    {
        $received = [];
        add_action('tofu_validation_failed', function ($errors, $config) use (&$received) {
            $received = ['errors' => $errors, 'config' => $config];
        }, 10, 2);

        $form = $this->makeForm();
        // 'name' is required, so an empty submission fails validation.
        $result = $form->processInput(['name' => ''], []);

        $this->assertFalse($result['success']);
        $this->assertSame('input', $result['next']);
        $this->assertArrayHasKey('name', $received['errors'] ?? []);
        $this->assertSame('contact', $received['config']->key ?? null);
    }

    public function testValidationFailedDoesNotFireOnASuccessfulSubmission(): void
    {
        $fired = 0;
        add_action('tofu_validation_failed', function () use (&$fired) {
            $fired++;
        });

        $form = $this->makeForm();
        $form->processInput(['name' => 'Taro'], []);

        $this->assertSame(0, $fired);
    }

    /**
     * redirect() ends in wp_safe_redirect() + exit; the bootstrap stub records
     * the target and throws in place of that exit.
     */
    private function capturedRedirect(Form $form, string $action): string
    {
        try {
            $form->redirect($action);
        } catch (\RuntimeException) {
            // expected — stands in for exit
        }

        return $GLOBALS['__tofu_redirects'][0] ?? '';
    }

    public function testRedirectUrlFilterCanRewriteTheTarget(): void
    {
        add_filter('tofu_redirect_url', function ($url, $action) {
            return $action === 'result' ? $url . '?submitted=1' : $url;
        }, 10, 2);

        $form = $this->makeForm();

        $this->assertSame('/contact/result/?submitted=1', $this->capturedRedirect($form, 'result'));
    }

    public function testRedirectUrlFilterIsIgnoredWhenItReturnsANonString(): void
    {
        add_filter('tofu_redirect_url', fn () => ['not', 'a', 'string']);

        $form = $this->makeForm();

        $this->assertSame('/contact/', $this->capturedRedirect($form, 'input'));
    }

    public function testRedirectUrlFilterIsIgnoredWhenItReturnsAnEmptyString(): void
    {
        add_filter('tofu_redirect_url', fn () => '');

        $form = $this->makeForm();

        $this->assertSame('/contact/', $this->capturedRedirect($form, 'input'));
    }
}
