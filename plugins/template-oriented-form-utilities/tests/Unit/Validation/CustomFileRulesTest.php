<?php

namespace TofuPlugin\Tests\Unit\Validation;

use TofuPlugin\Tests\Unit\BaseTestCase;
use TofuPlugin\Tests\Unit\Validation\Fixtures\Corpus;
use TofuPlugin\Tests\Unit\Validation\Support\EngineProbe;

/**
 * Pins the three file rules TOFU adds on top of the generic ones
 * (RequiredFileRule, MaxMbRule, MimeTypeRule in src/Validation/Rules/) and,
 * most importantly, WHY `custom_required_file` exists instead of the
 * built-in `required`.
 */
class CustomFileRulesTest extends BaseTestCase
{
    private function freshUpload(): array
    {
        return [
            'name' => 'sample.txt', 'type' => 'text/plain',
            'tmp_name' => Corpus::samplePath(), 'error' => \UPLOAD_ERR_OK,
            'size' => filesize(Corpus::samplePath()),
        ];
    }

    private function noFileSelected(): array
    {
        return ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => \UPLOAD_ERR_NO_FILE, 'size' => 0];
    }

    // -----------------------------------------------------------------
    // The four-quadrant case: `required` is wrong in BOTH directions for
    // file fields, which is the entire justification for a custom rule.
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function requiredLabelProvider(): array
    {
        return [
            'required'             => ['required'],
            'required_file'        => ['required_file'],
            'custom_required_file' => ['custom_required_file'],
        ];
    }

    /**
     * Plain `required` understands file fields now.
     *
     * It used to pass here: the $_FILES entry for "no file chosen" is a
     * non-empty five-key array, so an emptiness test said a file was
     * present. All three labels share one presence test, so all three agree.
     *
     * @dataProvider requiredLabelProvider
     */
    public function testNoFileSelectedFailsUnderEveryRequiredLabel(string $label): void
    {
        $result = EngineProbe::run(['attachment' => $this->noFileSelected()], ['attachment' => $label]);

        $this->assertTrue($result['fails'], "'{$label}' must reject an empty file input.");
    }

    /**
     * A carried-over upload is proven by the SERVER's session record, not by
     * the client-supplied hidden field.
     *
     * EngineProbe has no Form behind it, so nothing is verified here — which
     * is exactly the forgery case: a POST claiming an upload, with no
     * matching file on the server, must not satisfy the rule. The genuine
     * confirm-step path is covered end-to-end in
     * ValidationModelIntegrationTest, where a real session record exists.
     *
     * @dataProvider requiredLabelProvider
     */
    public function testUnverifiedUploadClaimNeverSatisfiesARequiredLabel(string $label): void
    {
        $result = EngineProbe::run(
            ['__tofu_uploaded_files' => ['attachment' => 'forged-or-stale-id']],
            ['attachment' => $label],
        );

        $this->assertTrue(
            $result['fails'],
            "'{$label}' must not accept an upload claim the server cannot corroborate."
        );
    }

    /**
     * `required_file` is the current name and `custom_required_file` the
     * original one. They are the same class, so they must never disagree —
     * this walks every value in the corpus's repertoire through both.
     */
    public function testRequiredFileAndItsLegacyLabelBehaveIdentically(): void
    {
        foreach (Corpus::values() as $name => $value) {
            $current = EngineProbe::run(['attachment' => $value], ['attachment' => 'required_file']);
            $legacy  = EngineProbe::run(['attachment' => $value], ['attachment' => 'custom_required_file']);

            $this->assertSame(
                $current,
                $legacy,
                "required_file and custom_required_file disagreed for the '{$name}' value."
            );
        }
    }

    public function testRequiredFileFailsWhenNeitherAFreshUploadNorASessionRecordExists(): void
    {
        $result = EngineProbe::run([], ['attachment' => 'required_file']);
        $this->assertTrue($result['fails']);
    }

    public function testBothRulesPassOnAGenuineFreshUpload(): void
    {
        $required = EngineProbe::run(['attachment' => $this->freshUpload()], ['attachment' => 'required']);
        $custom   = EngineProbe::run(['attachment' => $this->freshUpload()], ['attachment' => 'custom_required_file']);

        $this->assertFalse($required['fails']);
        $this->assertFalse($custom['fails']);
    }

    // -----------------------------------------------------------------
    // max_mb
    // -----------------------------------------------------------------

    public function testMaxMbPassesUnderTheLimit(): void
    {
        $file = ['tmp_name' => Corpus::samplePath(), 'size' => 1024];
        $result = EngineProbe::run(['attachment' => $file], ['attachment' => 'max_mb:5']);
        $this->assertFalse($result['fails']);
    }

    public function testMaxMbPassesExactlyAtTheBoundary(): void
    {
        $file = ['tmp_name' => Corpus::samplePath(), 'size' => 5 * 1024 * 1024];
        $result = EngineProbe::run(['attachment' => $file], ['attachment' => 'max_mb:5']);
        $this->assertFalse($result['fails'], 'Exactly 5MB must satisfy max_mb:5 (<=, not <).');
    }

    public function testMaxMbFailsOneByteOverTheBoundary(): void
    {
        $file = ['tmp_name' => Corpus::samplePath(), 'size' => 5 * 1024 * 1024 + 1];
        $result = EngineProbe::run(['attachment' => $file], ['attachment' => 'max_mb:5']);
        $this->assertTrue($result['fails']);
    }

    public function testMaxMbFailsWhenSizeIsNonNumeric(): void
    {
        $file = ['tmp_name' => Corpus::samplePath(), 'size' => 'not-a-number'];
        $result = EngineProbe::run(['attachment' => $file], ['attachment' => 'max_mb:5']);
        $this->assertTrue($result['fails']);
    }

    public function testMaxMbFailsWhenSizeKeyIsMissing(): void
    {
        $file = ['tmp_name' => Corpus::samplePath()];
        $result = EngineProbe::run(['attachment' => $file], ['attachment' => 'max_mb:5']);
        $this->assertTrue($result['fails']);
    }

    public function testMaxMbSelfSkipsWhenTmpNameIsEmpty(): void
    {
        $file = ['tmp_name' => '', 'size' => 999999999];
        $result = EngineProbe::run(['attachment' => $file], ['attachment' => 'max_mb:5']);
        $this->assertFalse($result['fails'], 'No selected file means max_mb has nothing to check and must pass.');
    }

    // -----------------------------------------------------------------
    // mime_type
    // -----------------------------------------------------------------

    public function testMimeTypePassesForAMatchingType(): void
    {
        $result = EngineProbe::run(
            ['attachment' => $this->freshUpload()],
            ['attachment' => 'mime_type:text/plain,application/pdf'],
        );
        $this->assertFalse($result['fails']);
    }

    public function testMimeTypeFailsForANonMatchingType(): void
    {
        $result = EngineProbe::run(
            ['attachment' => $this->freshUpload()],
            ['attachment' => 'mime_type:application/pdf,image/jpeg'],
        );
        $this->assertTrue($result['fails']);
    }

    public function testMimeTypeSniffsActualFileContentNotTheClientSuppliedType(): void
    {
        // The client-supplied 'type' claims application/pdf, but the real
        // file on disk is plain text — finfo() must win.
        $file = $this->freshUpload();
        $file['type'] = 'application/pdf';

        $result = EngineProbe::run(['attachment' => $file], ['attachment' => 'mime_type:application/pdf']);
        $this->assertTrue($result['fails'], 'A spoofed Content-Type must not bypass the mime_type check.');
    }

    public function testMimeTypeWithZeroParametersThrows(): void
    {
        $result = EngineProbe::run(['attachment' => $this->freshUpload()], ['attachment' => 'mime_type']);
        $this->assertArrayHasKey('throws', $result, 'mime_type with no allowed types must fail loudly, not silently pass.');
        $this->assertSame(\InvalidArgumentException::class, $result['throws']);
    }

    public function testMimeTypeSelfSkipsWhenTmpNameIsEmpty(): void
    {
        $result = EngineProbe::run(['attachment' => $this->noFileSelected()], ['attachment' => 'mime_type:text/plain']);
        $this->assertFalse($result['fails']);
    }

    // -----------------------------------------------------------------
    // Edge cases below are pinned because Phase 1 could plausibly change
    // them by accident. Several document warts rather than good behaviour —
    // each is labelled so a deliberate fix stays distinguishable from a
    // regression.
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{0: int}>
     */
    public static function failingUploadErrorCodeProvider(): array
    {
        return [
            'UPLOAD_ERR_INI_SIZE'   => [\UPLOAD_ERR_INI_SIZE],
            'UPLOAD_ERR_FORM_SIZE'  => [\UPLOAD_ERR_FORM_SIZE],
            'UPLOAD_ERR_PARTIAL'    => [\UPLOAD_ERR_PARTIAL],
            'UPLOAD_ERR_NO_FILE'    => [\UPLOAD_ERR_NO_FILE],
            'UPLOAD_ERR_NO_TMP_DIR' => [\UPLOAD_ERR_NO_TMP_DIR],
            'UPLOAD_ERR_CANT_WRITE' => [\UPLOAD_ERR_CANT_WRITE],
        ];
    }

    /**
     * Only UPLOAD_ERR_OK satisfies custom_required_file — every other
     * PHP upload error code counts as "no usable file".
     *
     * @dataProvider failingUploadErrorCodeProvider
     */
    public function testCustomRequiredFileRejectsEveryNonOkUploadErrorCode(int $errorCode): void
    {
        $result = EngineProbe::run(
            ['attachment' => ['name' => 'x', 'tmp_name' => '', 'error' => $errorCode, 'size' => 1]],
            ['attachment' => 'custom_required_file'],
        );

        $this->assertTrue($result['fails']);
    }

    /**
     * KNOWN UX WART (not a correctness bug, and out of scope to fix here).
     *
     * When an upload exceeds PHP's own `upload_max_filesize`, PHP hands us
     * UPLOAD_ERR_INI_SIZE with an EMPTY tmp_name. `max_mb` self-skips on an
     * empty tmp_name, so it never reports "too large" — the only rule that
     * speaks up is `custom_required_file`, and it says the field is
     * REQUIRED. The visitor uploaded a file that was simply too big and is
     * told they uploaded nothing.
     *
     * Pinned so the replacement reproduces it rather than changing it by
     * accident; improving the message is a deliberate follow-up.
     */
    public function testOversizedPerPhpIniUploadReportsRequiredNotTooLarge_KNOWN_WART(): void
    {
        $tooBigForPhpIni = ['name' => 'huge.pdf', 'tmp_name' => '', 'error' => \UPLOAD_ERR_INI_SIZE, 'size' => 0];

        $result = EngineProbe::run(
            ['attachment' => $tooBigForPhpIni],
            ['attachment' => 'custom_required_file|max_mb:5'],
        );

        $this->assertTrue($result['fails']);
        $this->assertStringContainsString(
            'required',
            $result['errors']['attachment'],
            'KNOWN WART: the surfaced message is the required-field one, never a size message.'
        );
    }

    /**
     * custom_required_file treats the session record as present only when
     * it is truthy (`!empty`). Upload IDs are generated by
     * UploadedFile::__construct() as bin2hex(random_bytes(16)) — 32 hex
     * characters, which can never be falsy — so this is safe today. It is
     * pinned as a CONSTRAINT: if ID generation ever changes to something
     * that could produce '0' or '', already-uploaded files would silently
     * start failing the confirm step.
     *
     * @dataProvider falsySessionIdProvider
     */
    public function testCustomRequiredFileTreatsFalsySessionIdAsNoFile(mixed $falsyId): void
    {
        $result = EngineProbe::run(
            ['__tofu_uploaded_files' => ['attachment' => $falsyId]],
            ['attachment' => 'custom_required_file'],
        );

        $this->assertTrue($result['fails']);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function falsySessionIdProvider(): array
    {
        return [
            'empty string' => [''],
            'string zero'  => ['0'],
            'int zero'     => [0],
            'null'         => [null],
            'false'        => [false],
        ];
    }

    public function testCustomRequiredFileIgnoresASessionRecordBelongingToAnotherField(): void
    {
        $result = EngineProbe::run(
            ['__tofu_uploaded_files' => ['some_other_field' => 'valid-looking-id']],
            ['attachment' => 'custom_required_file'],
        );

        $this->assertTrue($result['fails']);
    }

    public function testCustomRequiredFileToleratesANonArrayUploadedFilesValue(): void
    {
        // Defensive: the hidden field is attacker-controllable, so a scalar
        // here must fail closed rather than raise a PHP error.
        $result = EngineProbe::run(
            ['__tofu_uploaded_files' => 'not-an-array'],
            ['attachment' => 'custom_required_file'],
        );

        $this->assertTrue($result['fails']);
    }

    public function testMaxMbAcceptsAFractionalLimit(): void
    {
        $under = EngineProbe::run(['a' => ['tmp_name' => Corpus::samplePath(), 'size' => 1024]], ['a' => 'max_mb:0.5']);
        $over  = EngineProbe::run(['a' => ['tmp_name' => Corpus::samplePath(), 'size' => 1048576]], ['a' => 'max_mb:0.5']);

        $this->assertFalse($under['fails'], '1KB is under a 0.5MB limit.');
        $this->assertTrue($over['fails'], '1MB exceeds a 0.5MB limit.');
    }

    /**
     * KNOWN WART: a non-numeric max_mb parameter is cast to 0.0 rather than
     * rejected, so a typo like `max_mb:5mb` silently rejects EVERY upload
     * instead of erroring at config-parse time. Compare `mime_type`, which
     * does throw when misconfigured.
     *
     * Pinned as current behaviour; the in-house engine should arguably
     * throw here instead, but that is a deliberate change, not a silent one.
     */
    public function testMaxMbWithNonNumericParameterSilentlyRejectsEverything_KNOWN_WART(): void
    {
        $tinyFile = ['tmp_name' => Corpus::samplePath(), 'size' => 1];

        $result = EngineProbe::run(['a' => $tinyFile], ['a' => 'max_mb:abc']);

        $this->assertTrue($result['fails'], 'KNOWN WART: "abc" casts to 0.0MB, so even a 1-byte file fails.');
        $this->assertArrayNotHasKey('throws', $result, 'It fails silently rather than throwing — that is the wart.');
    }

    public function testMaxMbWithNoParameterThrows(): void
    {
        $result = EngineProbe::run(
            ['a' => ['tmp_name' => Corpus::samplePath(), 'size' => 1]],
            ['a' => 'max_mb'],
        );

        $this->assertArrayHasKey('throws', $result);
    }

    /**
     * Documents a real feature boundary: PHP delivers a `name="files[]"`
     * multi-upload as an array-of-arrays ($_FILES['a']['tmp_name'] is
     * itself a list). None of the three custom rules understand that
     * shape, so multiple files per field are NOT supported by TOFU today.
     *
     * Pinned so the boundary is explicit rather than folklore — and so
     * that if Phase 1 ever adds support, it is a deliberate feature with a
     * failing test to flip, not an accident.
     */
    public function testMultipleFileUploadShapeIsNotSupportedByAnyCustomRule(): void
    {
        $sample = Corpus::samplePath();
        $multiFile = [
            'name' => ['x.txt', 'y.txt'],
            'type' => ['text/plain', 'text/plain'],
            'tmp_name' => [$sample, $sample],
            'error' => [\UPLOAD_ERR_OK, \UPLOAD_ERR_OK],
            'size' => [1, 1],
        ];

        $required = EngineProbe::run(['a' => $multiFile], ['a' => 'custom_required_file']);
        $maxMb    = EngineProbe::run(['a' => $multiFile], ['a' => 'max_mb:5']);

        $this->assertTrue($required['fails'], 'custom_required_file does not recognise the multi-file shape.');
        $this->assertTrue($maxMb['fails'], 'max_mb does not recognise the multi-file shape.');
    }
}
