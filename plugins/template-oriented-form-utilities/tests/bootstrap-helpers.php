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
 * Suppress setcookie() calls during unit tests.
 * Session::getSessionId() calls setcookie() which would trigger
 * "Cannot modify header information" errors since PHPUnit has already
 * produced output by the time tests run.
 */
function setcookie(
    string $name,
    string $value = '',
    array|int $expires_or_options = 0,
    string $path = '',
    string $domain = '',
    bool $secure = false,
    bool $httponly = false
): bool {
    return true;
}
