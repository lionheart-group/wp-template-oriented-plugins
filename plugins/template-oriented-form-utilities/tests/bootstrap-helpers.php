<?php

/**
 * Namespace-level function mocks for unit tests.
 *
 * PHP resolves unqualified function calls within a namespace by first looking
 * for a function defined in that namespace, then falling back to the global
 * namespace. Defining these functions here intercepts the calls made by
 * TofuPlugin classes without touching the built-in PHP functions globally.
 */

namespace TofuPlugin\Helpers;

/**
 * Intercept setcookie() during unit tests.
 *
 * Calling the real one would emit "Cannot modify header information",
 * because PHPUnit has already written output by the time tests run. Each
 * call is also recorded, so tests can assert on WHETHER a cookie was issued
 * — the point of Session's read/issue split is that merely reading a
 * session must not send one, and that is only observable from here.
 *
 * @var array<int, array{name: string, value: string, options: array<string, mixed>|int}>
 */
$GLOBALS['__tofu_setcookie_calls'] = [];

function setcookie(
    string $name,
    string $value = '',
    array|int $expires_or_options = 0,
    string $path = '',
    string $domain = '',
    bool $secure = false,
    bool $httponly = false
): bool {
    $GLOBALS['__tofu_setcookie_calls'][] = [
        'name'    => $name,
        'value'   => $value,
        'options' => $expires_or_options,
    ];

    return true;
}
