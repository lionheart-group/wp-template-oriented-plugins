<?php

namespace TonkatsuPlugin\Init;

use TonkatsuPlugin\Consts;
use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Helpers\Url;
use TonkatsuPlugin\Models\Context;
use TonkatsuPlugin\Models\RedirectLog;
use TonkatsuPlugin\Structure\RedirectConfig;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Runs the redirects the theme registered with Seo::registerRedirect().
 *
 * The decision is the pure match(); redirect() only reads the request and
 * sends the response.
 */
class Redirects
{
    /**
     * `template_redirect` priority: before core's redirect_canonical (10),
     * which would otherwise guess a post for an old URL first.
     */
    public const PRIORITY = 0;

    /**
     * Set while TONKATSU's own wp_safe_redirect() runs, so the configured
     * hosts are allowed for that call only and not for every other
     * redirect on the site (`redirect_to` on the login screen, …).
     *
     * @var bool
     */
    protected static bool $redirecting = false;

    /**
     * Register the hooks. Call once from the plugin bootstrap.
     */
    public static function register(): void
    {
        add_action('template_redirect', [static::class, 'redirect'], self::PRIORITY);
        add_filter('allowed_redirect_hosts', [static::class, 'filterAllowedHosts']);

        // Create the log tables once SiteConfig says they are wanted. A closure,
        // because `init` passes an empty string maybeInstall() would take as its installer.
        add_action('init', static function (): void {
            RedirectLog::maybeInstall();
        }, Consts::REDIRECT_LOG_INIT_PRIORITY);
    }

    /**
     * Redirect, or answer 410, when the request matches a registered source.
     */
    public static function redirect(): void
    {
        $redirects = Seo::getRedirects();
        if ($redirects === []) {
            return;
        }

        $uri = Context::requestUri();
        $home = home_url('/');
        $query = wp_parse_url($uri, PHP_URL_QUERY);

        $match = self::match($redirects, Context::relativePath($uri, $home), is_string($query) ? $query : '', $home);
        if ($match === null) {
            return;
        }

        if ($match['location'] === null) {
            self::log($match, $uri);
            self::gone();
            return;
        }

        static::$redirecting = true;

        // wp_safe_redirect() would send a visitor to /wp-admin/ when the host
        // is not allowed; leave the request alone instead.
        if (wp_validate_redirect($match['location'], '') !== '') {
            self::log($match, $uri);
            wp_safe_redirect($match['location'], $match['status'], 'TONKATSU');
            exit;
        }

        static::$redirecting = false;
    }

    /**
     * Allow the hosts of the configured absolute targets, during TONKATSU's redirect only.
     *
     * Hosts are taken from `to` as written, never from the request, so a
     * regex target that ends up as `//evil.example/` is still refused.
     *
     * @param mixed $hosts
     * @return mixed
     */
    public static function filterAllowedHosts(mixed $hosts): mixed
    {
        if (!static::$redirecting || !is_array($hosts)) {
            return $hosts;
        }

        return array_values(array_unique(array_merge($hosts, self::hosts(Seo::getRedirects()))));
    }

    /**
     * Hosts of the absolute `to` URLs.
     *
     * @param list<RedirectConfig> $redirects
     * @return list<string>
     */
    public static function hosts(array $redirects): array
    {
        $hosts = [];

        foreach ($redirects as $config) {
            if ($config->to === null || Url::isRootRelative($config->to)) {
                continue;
            }

            $host = wp_parse_url($config->to, PHP_URL_HOST);
            if (is_string($host) && $host !== '' && !str_contains($host, '$')) {
                $hosts[] = strtolower($host);
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * Find the redirect for a request.
     *
     * Exact sources are checked first, then prefixes (longest first), then
     * regexes in registration order. A redirect whose result is the request
     * itself is skipped, so a regex can never loop.
     *
     * @param list<RedirectConfig> $redirects
     * @param string $path    The request path relative to the home URL, as
     *                        Context::relativePath() returns it (decoded, no
     *                        slashes at either end; '' is the front page).
     * @param string $query   The request's query string, without '?'.
     * @param string $homeUrl home_url('/'); root-relative targets are relative to it.
     * @return ?array{status: int, location: ?string, config: RedirectConfig}
     *     location is null for 410; config is the redirect that matched.
     */
    public static function match(array $redirects, string $path, string $query, string $homeUrl): ?array
    {
        foreach (self::candidates($redirects, $path) as [$config, $remainder, $groups]) {
            if ($config->to === null) {
                return ['status' => $config->status, 'location' => null, 'config' => $config];
            }

            $location = self::location($config, $remainder, $groups, $query, $homeUrl);

            if (self::isRequest($location, $path, $query, $homeUrl)) {
                continue;
            }

            return ['status' => $config->status, 'location' => $location, 'config' => $config];
        }

        return null;
    }

    /**
     * Whether a redirect's target is itself redirected (a chain).
     *
     * Only same-site targets without `$n` are checked.
     *
     * @param RedirectConfig $config
     * @param list<RedirectConfig> $redirects Every registered redirect.
     * @param string $homeUrl
     * @return bool
     */
    public static function isChained(RedirectConfig $config, array $redirects, string $homeUrl): bool
    {
        if ($config->to === null || ($config->type === RedirectConfig::TYPE_REGEX && str_contains($config->to, '$'))) {
            return false;
        }

        $target = self::sitePath($config->to, $homeUrl);
        if ($target === null) {
            return false;
        }

        $query = wp_parse_url($config->to, PHP_URL_QUERY);
        $others = array_values(array_filter($redirects, fn (RedirectConfig $other) => $other !== $config));

        return self::match($others, $target, is_string($query) ? $query : '', $homeUrl) !== null;
    }

    /**
     * Whether an exact redirect takes over a path registered with Seo::registerPage().
     *
     * @param RedirectConfig $config
     * @return bool
     */
    public static function hidesRegisteredPage(RedirectConfig $config): bool
    {
        return $config->type === RedirectConfig::TYPE_EXACT && Seo::getPage($config->path) !== null;
    }

    /**
     * The path of a same-site URL relative to the home URL, or null for another host.
     *
     * Root-relative URLs are relative to the home URL, like `to`.
     *
     * @param string $url
     * @param string $homeUrl
     * @return ?string
     */
    public static function sitePath(string $url, string $homeUrl): ?string
    {
        if (Url::isRootRelative($url)) {
            return Seo::normalizePath($url);
        }

        $host = wp_parse_url($url, PHP_URL_HOST);
        $homeHost = wp_parse_url($homeUrl, PHP_URL_HOST);
        if (!is_string($host) || !is_string($homeHost) || strcasecmp($host, $homeHost) !== 0) {
            return null;
        }

        return Context::relativePath($url, $homeUrl);
    }

    /**
     * Matching redirects in the order they apply.
     *
     * @param list<RedirectConfig> $redirects
     * @param string $path
     * @return \Generator<array{RedirectConfig, string, array<int|string, string>}>
     *     The config, the rest of the path below a prefix, and the regex groups.
     */
    private static function candidates(array $redirects, string $path): \Generator
    {
        foreach ($redirects as $config) {
            if ($config->type === RedirectConfig::TYPE_EXACT && $config->path === $path) {
                yield [$config, '', []];
            }
        }

        $prefixes = array_values(array_filter($redirects, fn (RedirectConfig $config) => $config->type === RedirectConfig::TYPE_PREFIX));
        usort($prefixes, fn (RedirectConfig $a, RedirectConfig $b) => strlen($b->path) <=> strlen($a->path));

        foreach ($prefixes as $config) {
            if ($path === $config->path) {
                yield [$config, '', []];
            } elseif (str_starts_with($path, $config->path . '/')) {
                yield [$config, substr($path, strlen($config->path) + 1), []];
            }
        }

        foreach ($redirects as $config) {
            if ($config->type === RedirectConfig::TYPE_REGEX && @preg_match($config->pattern(), $path, $groups) === 1) {
                yield [$config, '', $groups];
            }
        }
    }

    /**
     * Build the absolute target URL.
     *
     * @param RedirectConfig $config
     * @param string $remainder Decoded path below a prefix source.
     * @param array<int|string, string> $groups Decoded regex groups.
     * @param string $query
     * @param string $homeUrl
     * @return string
     */
    private static function location(RedirectConfig $config, string $remainder, array $groups, string $query, string $homeUrl): string
    {
        $to = (string) $config->to;

        // Split off the target's own query string and fragment
        $cut = strcspn($to, '?#');
        $base = substr($to, 0, $cut);
        $suffix = substr($to, $cut);

        if ($remainder !== '') {
            // The trailing slash follows `to`, except after a file name (/old-dir/a.pdf)
            $segments = explode('/', $remainder);
            $slash = str_ends_with($base, '/') && !str_contains((string) end($segments), '.');
            $base = rtrim($base, '/') . '/' . Url::encodePath($remainder) . ($slash ? '/' : '');
        }

        if ($groups !== []) {
            $base = self::substitute($base, $groups);
            $suffix = self::substitute($suffix, $groups);
        }

        // Carry the request's query string over, unless the target has its own
        if ($query !== '' && !str_contains($to, '?')) {
            $hash = strpos($suffix, '#');
            $suffix = $hash === false
                ? '?' . $query . $suffix
                : '?' . $query . substr($suffix, $hash);
        }

        $location = $base . $suffix;

        return Url::isRootRelative($location) ? rtrim($homeUrl, '/') . $location : $location;
    }

    /**
     * Replace `$1` / `${1}` with the regex groups, percent-encoded per segment.
     *
     * @param string $value
     * @param array<int|string, string> $groups
     * @return string
     */
    private static function substitute(string $value, array $groups): string
    {
        return (string) preg_replace_callback(
            '/\$(?:(\d+)|\{(\d+)\})/',
            static function (array $m) use ($groups): string {
                $index = (int) ($m[1] !== '' ? $m[1] : $m[2]);
                return Url::encodePath($groups[$index] ?? '');
            },
            $value
        );
    }

    /**
     * Whether a location is the request itself (same path and query string).
     *
     * @param string $location
     * @param string $path
     * @param string $query
     * @param string $homeUrl
     * @return bool
     */
    private static function isRequest(string $location, string $path, string $query, string $homeUrl): bool
    {
        if (self::sitePath($location, $homeUrl) !== $path) {
            return false;
        }

        $targetQuery = wp_parse_url($location, PHP_URL_QUERY);

        return (is_string($targetQuery) ? $targetQuery : '') === $query;
    }

    /**
     * Record the redirect about to be answered (when SiteConfig::$logRedirects is on).
     *
     * @param array{status: int, location: ?string, config: RedirectConfig} $match
     * @param string $uri The request URI.
     */
    private static function log(array $match, string $uri): void
    {
        $referrer = isset($_SERVER['HTTP_REFERER']) ? sanitize_url(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        RedirectLog::record($match['config'], $uri, $match['location'], $referrer, $userAgent);
    }

    /**
     * Answer 410 Gone with the theme's 404 template.
     */
    private static function gone(): void
    {
        global $wp_query;

        if ($wp_query instanceof \WP_Query) {
            $wp_query->set_404();
        }

        // redirect_canonical() would try to guess a post for a 404
        remove_action('template_redirect', 'redirect_canonical');

        status_header(410);
        nocache_headers();
    }
}
