<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\UploadedFileInspector;
use TofuPlugin\Validation\Support\Value;

/**
 * The value must be a successfully received upload, optionally within a
 * size range and of an allowed type:
 *
 *     uploaded_file
 *     uploaded_file:0,5M
 *     uploaded_file:0,5M,image/jpeg,image/png
 *
 * Like every file rule it skips silently when no file was chosen, so an
 * optional upload does not report an error; pair it with
 * `custom_required_file` to make one mandatory.
 *
 * Size limits share Value::bytes() with `max_mb` and type checking shares
 * the content sniff with `mime_type`, so the built-in and plugin file rules
 * cannot drift apart.
 */
class UploadedFileRule extends Rule
{
    protected string $message = 'rule.uploaded_file';

    public function fillParameters(array $params): self
    {
        $this->params['min_size'] = $params[0] ?? null;
        $this->params['max_size'] = $params[1] ?? null;
        $this->params['allowed_types'] = array_values(array_slice($params, 2));

        return $this;
    }

    public function check(mixed $value): bool
    {
        if (UploadedFileInspector::shouldSkip($value)) {
            return true;
        }

        if (!UploadedFileInspector::isSuccessfulUpload($value)) {
            $this->message = 'rule.uploaded_file';

            return false;
        }

        $size = UploadedFileInspector::size($value);
        if ($size === null) {
            $this->message = 'rule.uploaded_file';

            return false;
        }

        $min = $this->parameter('min_size');
        if ($min !== null && $min !== '') {
            $minBytes = Value::bytes($min);
            if ($minBytes !== null && $size < $minBytes) {
                $this->message = 'rule.uploaded_file.min_size';

                return false;
            }
        }

        $max = $this->parameter('max_size');
        if ($max !== null && $max !== '') {
            $maxBytes = Value::bytes($max);
            if ($maxBytes !== null && $size > $maxBytes) {
                $this->message = 'rule.uploaded_file.max_size';

                return false;
            }
        }

        /** @var array<int, string> $allowed */
        $allowed = $this->parameter('allowed_types', []);
        if ($allowed !== []) {
            $detected = UploadedFileInspector::detectMimeType($value);
            if ($detected === null || !in_array($detected, $allowed, true)) {
                $this->message = 'rule.uploaded_file.type';

                return false;
            }
        }

        return true;
    }
}
