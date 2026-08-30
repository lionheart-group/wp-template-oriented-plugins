<?php

namespace TofuPlugin\Tests\Unit\Validation;

use TofuPlugin\Models\Form;
use TofuPlugin\Models\Validation;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;
use TofuPlugin\Structure\UploadedFile;
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

    /**
     * Seed a form the way the Form constructor would on a confirm-step
     * request: no $_FILES at all, but the session carrying both the
     * UploadedFile itself and the hidden map naming its ID.
     */
    private function seedRestoredUpload(Form $form, string $field, string $id): void
    {
        $form->getFiles()->addFile(new UploadedFile(
            id: $id,
            name: $field,
            fileName: 'sample.txt',
            mimeType: 'text/plain',
            tempName: Corpus::samplePath(),
            size: (int) filesize(Corpus::samplePath()),
        ));
        $form->getValues()->addValue('__tofu_uploaded_files', [$field => $id]);
    }

    public function testVerifySessionPassesForAFileFieldRestoredFromTheSession(): void
    {
        $form = $this->makeForm(new ValidationConfig(
            allows: ['attachment'],
            rules: ['attachment' => 'required_file'],
            names: ['attachment' => 'Attachment'],
        ));

        $this->seedRestoredUpload($form, 'attachment', 'previously-issued-id');

        $ok = $form->verifySession();

        $this->assertTrue($ok, 'A genuinely restored upload must satisfy the rule on the confirm step.');
        $this->assertFalse($form->getErrors()->hasErrors());
    }

    /**
     * The hidden `__tofu_uploaded_files` input travels with the form and is
     * therefore attacker-controlled. It only counts when the server holds a
     * file whose ID matches; a bare claim proves nothing.
     */
    public function testVerifySessionRejectsAnUploadClaimWithNoMatchingServerRecord(): void
    {
        $form = $this->makeForm(new ValidationConfig(
            allows: ['attachment'],
            rules: ['attachment' => 'required_file'],
            names: ['attachment' => 'Attachment'],
        ));

        // A claim, with nothing behind it.
        $form->getValues()->addValue('__tofu_uploaded_files', ['attachment' => 'forged-id']);

        $this->assertFalse($form->verifySession());
        $this->assertTrue($form->getErrors()->hasFieldErrors('attachment'));
    }

    public function testVerifySessionRejectsAnUploadClaimWhoseIdDoesNotMatch(): void
    {
        $form = $this->makeForm(new ValidationConfig(
            allows: ['attachment'],
            rules: ['attachment' => 'required_file'],
            names: ['attachment' => 'Attachment'],
        ));

        $this->seedRestoredUpload($form, 'attachment', 'the-real-id');
        // Overwrite the claim with a different ID than the stored file's.
        $form->getValues()->addValue('__tofu_uploaded_files', ['attachment' => 'a-different-id']);

        $this->assertFalse($form->verifySession());
        $this->assertTrue($form->getErrors()->hasFieldErrors('attachment'));
    }

    /**
     * Anything that passes a required check through the upload channel must
     * still have its file afterwards.
     *
     * Validation and the post-validation cleanup now share one verified set,
     * so "passed validation, then the file was discarded" — which would send
     * a confirmation page and an email with no attachment — cannot happen.
     */
    public function testAFileThatSatisfiedValidationSurvivesTheCleanup(): void
    {
        $form = $this->makeForm(new ValidationConfig(
            allows: ['attachment'],
            rules: ['attachment' => 'required_file'],
            names: ['attachment' => 'Attachment'],
        ));

        $this->seedRestoredUpload($form, 'attachment', 'previously-issued-id');

        $this->assertTrue($form->verifySession());
        $this->assertTrue(
            $form->getFiles()->hasFile('attachment'),
            'The upload that satisfied validation was discarded by the cleanup pass.'
        );
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
