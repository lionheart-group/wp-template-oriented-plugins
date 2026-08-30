<?php

namespace TofuPlugin\Validation\Rules;

/**
 * A file must be attached to this field.
 *
 * Runs exactly the same code as `required` — there is no check() here — and
 * differs by one flag: a value that is not a file never satisfies it.
 *
 * That flag is load-bearing rather than cosmetic. max_mb and mime_type both
 * skip silently when the value is not an array, so without it
 * `'attachment' => 'custom_required_file|max_mb:5|mime_type:application/pdf'`
 * would be satisfied by POSTing `attachment=abc`, and a mandatory
 * attachment could be bypassed with a one-line curl.
 *
 * Registered under two labels. `required_file` is the current name;
 * `custom_required_file` is the original one, from when the rule had to
 * avoid colliding with the departed library's names, and stays registered
 * because existing forms are written against it — an unknown rule name
 * throws at config-parse time, which would white screen a live form.
 *
 * Usage in rules: 'attachment' => 'required_file'
 */
class RequiredFileRule extends RequiredRule
{
    protected bool $fileOnly = true;
    protected string $message = 'rule.required_file';
}
