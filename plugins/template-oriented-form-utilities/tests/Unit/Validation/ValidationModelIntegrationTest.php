<?php

namespace TofuPlugin\Tests\Unit\Validation;

use TofuPlugin\Models\Form;
use TofuPlugin\Models\Validation;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;
use TofuPlugin\Structure\ValidationConfig;
use TofuPlugin\Tests\Unit\BaseTestCase;
use TofuPlugin\Tests\Unit\Validation\Fixtures\Corpus;

/**
 * End-to-end tests through the REAL TofuPlugin\Models\Validation::validate()
 * and TofuPlugin\Models\Form — as opposed to every other test in this
 * directory, which drives EngineProbe (the raw engine, wired identically to
 * production but bypassing Form/session plumbing).
 *
 * These exist because that plumbing has its own behaviour worth pinning:
 * the `allows` whitelist, the `after` hook, and — critically — the
 * confirm-step re-validation path (Form::verifySession(), src/Models/Form.php)
 * where `$_FILES` is never present and a session-restored value is the only
 * evidence a file (or any other field) exists.
 */
class ValidationModelIntegrationTest extends BaseTestCase
{
    private function makeForm(ValidationConfig $validation): Form
    {
        $config = new FormConfig(
            key: 'integration-test',
            name: 'Integration Test',
            mail: new MailConfig(
                fromEmail: 'noreply@example.com',
                fromName: 'Test',
                recipients: new MailRecipientsCollection([
                    new MailRecipientsConfig(
                        recipientEmail: 'admin@example.com',
                        subject: 'Test',
                        mailBody: 'Test body',
                    ),
                ]),
            ),
            validation: $validation,
        );

        return new Form($config);
    }

    // -----------------------------------------------------------------
    // Input step: $_POST + $_FILES validated together
    // -----------------------------------------------------------------

    public function testInputStepReportsErrorsForInvalidFields(): void
    {
        $form = $this->makeForm(new ValidationConfig(
            allows: ['name', 'email'],
            rules: ['name' => 'required', 'email' => 'required|email'],
            names: ['name' => 'Name', 'email' => 'Email'],
        ));

        (new Validation())->validate($form, ['name' => '', 'email' => 'not-an-email'], []);

        $this->assertTrue($form->getErrors()->hasErrors());
        $this->assertTrue($form->getErrors()->hasFieldErrors('name'));
        $this->assertTrue($form->getErrors()->hasFieldErrors('email'));
    }

    public function testInputStepPassesThroughValidFields(): void
    {
        $form = $this->makeForm(new ValidationConfig(
            allows: ['name', 'email'],
            rules: ['name' => 'required', 'email' => 'required|email'],
            names: ['name' => 'Name', 'email' => 'Email'],
        ));

        (new Validation())->validate($form, ['name' => 'Taro', 'email' => 'taro@example.com'], []);

        $this->assertFalse($form->getErrors()->hasErrors());
        $this->assertSame('Taro', $form->getValues()->getValue('name')->value);
    }

    public function testInputStepValidatesAnUploadedFileAlongsidePostFields(): void
    {
        $sample = Corpus::samplePath();
        $form = $this->makeForm(new ValidationConfig(
            allows: ['name', 'attachment'],
            rules: [
                'name' => 'required',
                'attachment' => 'custom_required_file|max_mb:5|mime_type:text/plain',
            ],
            names: ['name' => 'Name', 'attachment' => 'Attachment'],
        ));

        (new Validation())->validate(
            $form,
            ['name' => 'Taro'],
            ['attachment' => [
                'name' => 'sample.txt', 'type' => 'text/plain',
                'tmp_name' => $sample, 'error' => \UPLOAD_ERR_OK, 'size' => filesize($sample),
            ]],
        );

        $this->assertFalse($form->getErrors()->hasErrors());
    }

    // -----------------------------------------------------------------
    // allows: fields outside the whitelist never reach $values
    // -----------------------------------------------------------------

    public function testAllowsWhitelistFiltersOutUndeclaredFields(): void
    {
        $form = $this->makeForm(new ValidationConfig(
            allows: ['name'],
            rules: ['name' => 'required'],
            names: ['name' => 'Name'],
        ));

        (new Validation())->validate($form, ['name' => 'Taro', 'not_declared' => 'sneaky'], []);

        $this->assertNotNull($form->getValues()->getValue('name'));
        $this->assertNull($form->getValues()->getValue('not_declared'));
    }

    // -----------------------------------------------------------------
    // after: runs last, can add both values and errors
    // -----------------------------------------------------------------

    public function testAfterHookCanRejectAnOtherwiseValidValue(): void
    {
        $form = $this->makeForm(new ValidationConfig(
            allows: ['name'],
            rules: ['name' => 'required'],
            names: ['name' => 'Name'],
            after: function ($values, $errors): void {
                if ($values->getValue('name')?->value === 'Test') {
                    $errors->addError('name', 'The name "Test" is not allowed.');
                }
            },
        ));

        (new Validation())->validate($form, ['name' => 'Test'], []);

        $this->assertTrue($form->getErrors()->hasFieldErrors('name'));
    }

    public function testAfterHookCanAddAComputedValue(): void
    {
        $form = $this->makeForm(new ValidationConfig(
            allows: ['name', 'computed'],
            rules: ['name' => 'required'],
            names: ['name' => 'Name'],
            after: function ($values, $errors): void {
                $values->addValue('computed', 'set-by-after');
            },
        ));

        (new Validation())->validate($form, ['name' => 'Taro'], []);

        $this->assertSame('set-by-after', $form->getValues()->getValue('computed')->value);
    }

    // -----------------------------------------------------------------
    // Confirm step: Form::verifySession() re-validates from
    // $this->values->toArray() alone — no $_FILES, ever.
    // -----------------------------------------------------------------

    public function testVerifySessionPassesForAFileFieldBackedOnlyByTheUploadedFilesSessionRecord(): void
    {
        $form = $this->makeForm(new ValidationConfig(
            allows: ['attachment'],
            rules: ['attachment' => 'custom_required_file'],
            names: ['attachment' => 'Attachment'],
        ));

        // Simulate what the Form constructor would have restored from the
        // session on the confirm-step GET request: no $_FILES, just the
        // hidden field recording which upload ID belongs to this field.
        $form->getValues()->addValue('__tofu_uploaded_files', ['attachment' => 'previously-issued-id']);

        $ok = $form->verifySession();

        $this->assertTrue($ok, 'custom_required_file must pass using only the session-restored upload record.');
        $this->assertFalse($form->getErrors()->hasErrors());
    }

    public function testVerifySessionFailsWhenNoUploadedFilesRecordExists(): void
    {
        $form = $this->makeForm(new ValidationConfig(
            allows: ['attachment'],
            rules: ['attachment' => 'custom_required_file'],
            names: ['attachment' => 'Attachment'],
        ));

        $ok = $form->verifySession();

        $this->assertFalse($ok);
        $this->assertTrue($form->getErrors()->hasFieldErrors('attachment'));
    }

    public function testVerifySessionRunsTheAfterHookAgain(): void
    {
        $form = $this->makeForm(new ValidationConfig(
            allows: ['name'],
            rules: ['name' => 'required'],
            names: ['name' => 'Name'],
            after: function ($values, $errors): void {
                if ($values->getValue('name')?->value === 'Test') {
                    $errors->addError('name', 'The name "Test" is not allowed.');
                }
            },
        ));

        $form->getValues()->addValue('name', 'Test');

        $ok = $form->verifySession();

        $this->assertFalse($ok);
        $this->assertTrue($form->getErrors()->hasFieldErrors('name'));
    }

    /**
     * KNOWN, pre-existing bug — NOT introduced or fixed by this test suite.
     *
     * src/Models/Validation.php unconditionally calls wp_unslash() on the
     * incoming values (line 31). On the input step that is correct (WP has
     * just added magic-quote slashes to $_POST). On the confirm step,
     * Form::verifySession() feeds it $this->values->toArray() — values
     * that went through wp_unslash() ONCE ALREADY when they were first
     * stored. A second stripslashes() pass on already-clean data silently
     * eats any backslash the user legitimately typed (e.g. a Windows path).
     *
     * This is pinned here, not fixed, per the migration plan: it predates
     * the validation-engine replacement this test suite exists to de-risk,
     * and fixing it is out of scope for that change.
     */
    public function testVerifySessionDoubleUnslashesAlreadyCleanValues_KNOWN_BUG(): void
    {
        $form = $this->makeForm(new ValidationConfig(
            allows: ['path'],
            rules: [],
            names: [],
        ));

        $correct = 'C:\Users\test';
        $wpMagicQuoted = addslashes($correct);

        // Input step: one wp_unslash() pass correctly undoes WP's slashes.
        (new Validation())->validate($form, ['path' => $wpMagicQuoted], []);
        $this->assertSame($correct, $form->getValues()->getValue('path')->value);

        // Confirm step: verifySession() re-validates the ALREADY-CLEAN
        // value, applying a second, unwarranted stripslashes() pass.
        $form->verifySession();

        $doubleUnslashed = stripslashes($correct);
        $this->assertNotSame($correct, $doubleUnslashed, 'Sanity check: stripslashes must actually alter this literal backslash value.');
        $this->assertSame(
            $doubleUnslashed,
            $form->getValues()->getValue('path')->value,
            'KNOWN BUG: verifySession() corrupts a legitimate backslash by unslashing already-clean session data a second time.'
        );
    }
}
