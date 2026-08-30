<?php

namespace TofuPlugin\Validation\Support;

/**
 * Shared predicates for the file rules.
 *
 * Both the engine's built-in file rules (`uploaded_file`, `mimes`,
 * `extension`) and the plugin's own (`mime_type`, `max_mb`) answer the same
 * few questions, and they must answer them identically. Keeping the logic
 * here rather than duplicating it is what prevents the situation this
 * replaced, where the built-in rules were quietly broken while the
 * plugin's worked.
 */
final class UploadedFileInspector
{
    /**
     * Whether the rule should evaluate this value at all.
     *
     * File rules skip silently when no file was chosen, so that an optional
     * upload does not report a type or size error. Pair them with
     * `custom_required_file` to make an upload mandatory.
     */
    public static function shouldSkip(mixed $value): bool
    {
        return !is_array($value) || empty($value['tmp_name']);
    }

    /**
     * The MIME type sniffed from the file's actual CONTENT.
     *
     * Deliberately not the client-supplied `type`, which is trivially
     * spoofed — a text file renamed to .pdf must not pass a PDF check.
     */
    public static function detectMimeType(mixed $value): ?string
    {
        if (self::shouldSkip($value) || !is_array($value)) {
            return null;
        }

        $path = $value['tmp_name'];
        if (!is_string($path) || !is_file($path)) {
            return null;
        }

        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return is_string($detected) ? $detected : null;
    }

    /**
     * The file's declared extension, lowercased, taken from its original
     * name.
     */
    public static function extension(mixed $value): ?string
    {
        if (!is_array($value)) {
            return null;
        }

        $name = $value['name'] ?? null;
        if (!is_string($name) || $name === '') {
            return null;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);

        return $extension === '' ? null : strtolower($extension);
    }

    /**
     * The file's size in bytes, or null when it is unusable.
     */
    public static function size(mixed $value): int|float|null
    {
        if (!is_array($value) || !isset($value['size']) || !is_numeric($value['size'])) {
            return null;
        }

        return (float) $value['size'];
    }

    /**
     * Whether the array describes a successfully received upload.
     *
     * Note this does NOT call is_uploaded_file(): that only answers true
     * during the request PHP itself received the upload in, so it is always
     * false on the confirm step and in tests, which is exactly why the
     * built-in `uploaded_file` rule never worked here.
     */
    public static function isSuccessfulUpload(mixed $value): bool
    {
        return Value::isUploadedFileShape($value) && $value['error'] === UPLOAD_ERR_OK;
    }
}
