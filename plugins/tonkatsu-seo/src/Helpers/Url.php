<?php

namespace ToroPlugin\Helpers;

/**
 * URL checks shared by the Structure/ constructors and the Resolver.
 *
 * Pure PHP on purpose: the value objects validate on construction, and
 * they must stay constructible in unit tests without WordPress loaded.
 */
final class Url
{
    /**
     * Whether the value is an absolute http(s) URL or a root-relative path.
     *
     * Root-relative paths (`/wp-content/themes/foo/ogp.png`) are accepted
     * because that is how theme code most naturally writes them; they are
     * made absolute against the site's origin when output, see absolute().
     *
     * Non-ASCII characters are percent-encoded before checking, as
     * FILTER_VALIDATE_URL rejects them outright and Japanese slugs are
     * ordinary here.
     *
     * @param string $url
     * @return bool
     */
    public static function isValid(string $url): bool
    {
        if ($url === '' || preg_match('/\s/', $url) === 1) {
            return false;
        }

        if (self::isRootRelative($url)) {
            return true;
        }

        $encoded = (string) preg_replace_callback(
            '/[^\x21-\x7e]+/',
            static fn (array $m): string => rawurlencode($m[0]),
            $url
        );

        if (filter_var($encoded, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($encoded, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * Make a root-relative path absolute against the origin of $homeUrl.
     *
     * Absolute URLs are returned untouched. The origin is used rather than
     * the full home URL because a root-relative path is, by definition,
     * relative to the host root — also on a site installed in a
     * subdirectory.
     *
     * @param string $url
     * @param string $homeUrl
     * @return string
     */
    public static function absolute(string $url, string $homeUrl): string
    {
        if (!self::isRootRelative($url) || $homeUrl === '') {
            return $url;
        }

        $parts = parse_url($homeUrl);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin . $url;
    }

    /**
     * `/path`, but not the protocol-relative `//host/path`.
     *
     * @param string $url
     * @return bool
     */
    private static function isRootRelative(string $url): bool
    {
        return str_starts_with($url, '/') && !str_starts_with($url, '//');
    }
}
