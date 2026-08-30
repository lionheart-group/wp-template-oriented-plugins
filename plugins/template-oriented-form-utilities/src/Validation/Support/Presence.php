<?php

namespace TofuPlugin\Validation\Support;

use TofuPlugin\Validation\Attribute;

/**
 * "Does this field have an answer?" — the single definition, shared by
 * `required`, the whole `required_*` family, and `custom_required_file`.
 *
 * File fields make this harder than it looks, which is why one predicate
 * serves all of them rather than each rule guessing:
 *
 *  - The `$_FILES` entry for "no file chosen" is a non-empty five-key array,
 *    so a naive emptiness test says a file IS present when none was picked.
 *  - On the confirm step the field's key is absent entirely, even though a
 *    file was uploaded a request earlier — a naive test says nothing is
 *    there when something is.
 *
 * Both directions are wrong, and both are fixed here once. Whether an
 * earlier upload still counts is answered by the SERVER's own session
 * record (Attribute::hasVerifiedUpload()), never by the client-supplied
 * `__tofu_uploaded_files` input, so a forged hidden field cannot make an
 * empty field look answered.
 */
final class Presence
{
    /**
     * @param bool $fileOnly When true, only an actual file satisfies the
     *                       check — a scalar never does. This is what
     *                       separates `custom_required_file` from
     *                       `required`: without it, a mandatory attachment
     *                       could be satisfied by POSTing a plain string,
     *                       since max_mb and mime_type both skip
     *                       non-arrays.
     */
    public static function satisfied(?Attribute $attribute, mixed $value, bool $fileOnly = false): bool
    {
        if (Value::isUploadedFileShape($value)) {
            if ($value['error'] === UPLOAD_ERR_OK) {
                return true;
            }

            // UPLOAD_ERR_NO_FILE and friends are not necessarily a refusal:
            // re-rendering the page after a validation error resubmits an
            // empty file input while the previous upload still stands.
            return $attribute?->hasVerifiedUpload() ?? false;
        }

        // Confirm step: the key is gone from the input entirely and the
        // server's own record is the only evidence.
        if ($attribute?->hasVerifiedUpload() === true) {
            return true;
        }

        if ($fileOnly) {
            return false;
        }

        return !Value::isEmpty($value);
    }
}
