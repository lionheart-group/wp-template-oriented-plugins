<?php

namespace TonkatsuPlugin\Init;

use TonkatsuPlugin\Consts;
use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Helpers\Url;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Core sitemap provider for the URLs outside WordPress listed in
 * SitemapConfig::$extraUrls (`/wp-sitemap-static-1.xml`).
 *
 * Registered by Sitemap::registerStaticProvider(). The file lookups happen
 * only while a page of this sitemap is built, one file_exists/filemtime per
 * URL on that page.
 */
class SitemapProvider extends \WP_Sitemaps_Provider
{
    public function __construct()
    {
        $this->name        = Consts::SITEMAP_STATIC_PROVIDER;
        $this->object_type = Consts::SITEMAP_STATIC_PROVIDER;
    }

    /**
     * @param int    $page_num
     * @param string $object_subtype Unused; this provider has no subtypes.
     * @return list<array{loc: string, lastmod?: string}>
     */
    public function get_url_list($page_num, $object_subtype = '')
    {
        $homeUrl = home_url('/');
        $page = self::page(
            self::listed(Seo::getSite()->sitemap->extraUrlEntries(), $homeUrl),
            (int) $page_num,
            $this->maxUrls()
        );

        $root = self::root();
        $list = [];
        foreach ($page as $entry) {
            $file = $entry['lastmod'] === null ? self::fileFor($entry['loc'], $homeUrl, $root) : null;
            $lastmod = self::lastmod($entry['lastmod'], $file);

            $list[] = $lastmod === null ? ['loc' => $entry['loc']] : ['loc' => $entry['loc'], 'lastmod' => $lastmod];
        }

        return $list;
    }

    /**
     * @param string $object_subtype Unused; this provider has no subtypes.
     * @return int
     */
    public function get_max_num_pages($object_subtype = '')
    {
        return self::pageCount(
            count(self::listed(Seo::getSite()->sitemap->extraUrlEntries(), home_url('/'))),
            $this->maxUrls()
        );
    }

    /**
     * The directory that serves the home URL on this site, see rootDir().
     *
     * @return string
     */
    public static function root(): string
    {
        return self::rootDir((string) get_option('home'), (string) get_option('siteurl'), ABSPATH);
    }

    /**
     * URLs per sitemap page, as core decides it (`wp_sitemaps_max_urls`, 2000).
     *
     * @return int
     */
    protected function maxUrls(): int
    {
        $max = wp_sitemaps_get_max_urls($this->object_type);

        return is_int($max) && $max > 0 ? $max : 2000;
    }

    /**
     * Every extraUrls entry with what the sitemap and the admin page need.
     *
     * - `loc`: the absolute URL.
     * - `listed`: false for a URL on another host or scheme, which is left out.
     * - `file`: the file found for the URL, or null.
     * - `lastmod`: the value written in the sitemap, or null.
     *
     * @param list<array{url: string, lastmod: ?string}> $entries
     * @param string $homeUrl
     * @param string $root
     * @return list<array{url: string, loc: string, listed: bool, file: ?string, lastmod: ?string}>
     */
    public static function describe(array $entries, string $homeUrl, string $root): array
    {
        $described = [];
        foreach ($entries as $entry) {
            $loc = self::resolveUrl($entry['url'], $homeUrl);
            $listed = self::isSameSite($loc, $homeUrl);
            $file = $listed ? self::fileFor($loc, $homeUrl, $root) : null;

            $described[] = [
                'url'     => $entry['url'],
                'loc'     => $loc,
                'listed'  => $listed,
                'file'    => $file,
                'lastmod' => $listed ? self::lastmod($entry['lastmod'], $file) : null,
            ];
        }

        return $described;
    }

    /**
     * The entries that go into the sitemap: absolute, encoded, on the site's
     * scheme and host, each URL once.
     *
     * @param list<array{url: string, lastmod: ?string}> $entries
     * @param string $homeUrl
     * @return list<array{loc: string, lastmod: ?string}>
     */
    public static function listed(array $entries, string $homeUrl): array
    {
        $listed = [];
        foreach ($entries as $entry) {
            $loc = self::resolveUrl($entry['url'], $homeUrl);
            if (!isset($listed[$loc]) && self::isSameSite($loc, $homeUrl)) {
                $listed[$loc] = ['loc' => $loc, 'lastmod' => $entry['lastmod']];
            }
        }

        return array_values($listed);
    }

    /**
     * Make a root-relative URL absolute, as the rest of TONKATSU does, and
     * percent-encode what the sitemap protocol requires to be encoded.
     *
     * Relative to the host root, also on a site installed in a subdirectory.
     * Non-ASCII characters after the host (`/lp/日本語/`) are encoded; `%xx`
     * already written stays as it is, so an encoded and an unencoded spelling
     * of one URL give the same `loc`.
     *
     * @param string $url
     * @param string $homeUrl
     * @return string
     */
    public static function resolveUrl(string $url, string $homeUrl): string
    {
        if (Url::isRootRelative($url)) {
            return Url::absolute(self::encodeNonAscii($url), $homeUrl);
        }

        if (preg_match('#^([a-z][a-z0-9+.-]*://[^/?\#]*)(.*)$#is', $url, $m) === 1) {
            return $m[1] . self::encodeNonAscii($m[2]);
        }

        return $url;
    }

    /**
     * Percent-encode every byte outside printable ASCII.
     *
     * @param string $value
     * @return string
     */
    private static function encodeNonAscii(string $value): string
    {
        return (string) preg_replace_callback(
            '/[^\x21-\x7e]+/',
            static fn (array $m): string => rawurlencode($m[0]),
            $value
        );
    }

    /**
     * Whether an absolute URL has the home URL's scheme, host and port.
     *
     * The sitemap protocol only allows URLs with the sitemap's own protocol
     * and host, and the sitemap is served from the home URL, so
     * `http://example.com/lp/` is left out on `https://example.com/`.
     *
     * @param string $url
     * @param string $homeUrl
     * @return bool
     */
    public static function isSameSite(string $url, string $homeUrl): bool
    {
        if (Url::isRootRelative($url)) {
            return true;
        }

        $target = wp_parse_url($url);
        $home = wp_parse_url($homeUrl);
        if (!is_array($target) || !is_array($home) || !isset($target['host'], $home['host'])) {
            return false;
        }

        return strcasecmp($target['scheme'] ?? '', $home['scheme'] ?? '') === 0
            && strcasecmp($target['host'], $home['host']) === 0
            && ($target['port'] ?? null) === ($home['port'] ?? null);
    }

    /**
     * The decoded path of $url relative to the home URL's path, or null
     * when $url lies outside it.
     *
     * `https://example.com/blog/lp/a/` with the home URL
     * `https://example.com/blog/` gives `/lp/a/`.
     *
     * @param string $url
     * @param string $homeUrl
     * @return string|null
     */
    public static function pathFromHome(string $url, string $homeUrl): ?string
    {
        $path = wp_parse_url($url, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        $base = wp_parse_url($homeUrl, PHP_URL_PATH);
        $base = is_string($base) ? rtrim($base, '/') : '';

        if ($base !== '') {
            if ($path !== $base && !str_starts_with($path, $base . '/')) {
                return null;
            }
            $path = substr($path, strlen($base));
        }

        return rawurldecode($path !== '' ? $path : '/');
    }

    /**
     * The directory that serves the home URL.
     *
     * ABSPATH, unless WordPress lives in a subdirectory of the home URL
     * (Site Address differs from WordPress Address); then ABSPATH without
     * that subdirectory, as core's get_home_path() does.
     *
     * @param string $home    The `home` option.
     * @param string $siteUrl The `siteurl` option.
     * @param string $abspath
     * @return string With a trailing slash.
     */
    public static function rootDir(string $home, string $siteUrl, string $abspath): string
    {
        $abspath = rtrim(str_replace('\\', '/', $abspath), '/') . '/';
        $home = rtrim((string) preg_replace('#^https?://#i', '', $home), '/');
        $siteUrl = rtrim((string) preg_replace('#^https?://#i', '', $siteUrl), '/');

        if ($home === '' || strcasecmp($home, $siteUrl) === 0 || stripos($siteUrl, $home . '/') !== 0) {
            return $abspath;
        }

        $subdirectory = substr($siteUrl, strlen($home)) . '/';
        $position = strripos($abspath, $subdirectory);

        if ($position === false || $position + strlen($subdirectory) !== strlen($abspath)) {
            return $abspath;
        }

        return substr($abspath, 0, $position) . '/';
    }

    /**
     * The file found for a sitemap URL, or null.
     *
     * @param string $loc  Absolute URL.
     * @param string $homeUrl
     * @param string $root See rootDir().
     * @return string|null
     */
    public static function fileFor(string $loc, string $homeUrl, string $root): ?string
    {
        $path = self::pathFromHome($loc, $homeUrl);

        return $path === null ? null : self::localFile($path, $root);
    }

    /**
     * The file a URL path points to under $root, or null.
     *
     * - A path ending in `/` looks for `index.html`, then `index.php`.
     * - Any other path looks for that file.
     * - A path with a `..` segment, or one that resolves outside $root
     *   (through a symbolic link, say), gives null.
     *
     * @param string $path Decoded, relative to $root (`/lp/campaign/`).
     * @param string $root
     * @return string|null The real path of the file.
     */
    public static function localFile(string $path, string $root): ?string
    {
        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            return null;
        }

        if (in_array('..', explode('/', $path), true)) {
            return null;
        }

        $base = realpath($root);
        if ($base === false || !is_dir($base)) {
            return null;
        }
        $prefix = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $relative = ltrim($path, '/');
        $candidates = $relative === '' || str_ends_with($relative, '/')
            ? [$relative . 'index.html', $relative . 'index.php']
            : [$relative];

        foreach ($candidates as $candidate) {
            $real = realpath($prefix . $candidate);
            if ($real !== false && is_file($real) && str_starts_with($real, $prefix)) {
                return $real;
            }
        }

        return null;
    }

    /**
     * The sitemap's lastmod: the written value, else the file's modification
     * time as a W3C datetime (UTC), else null.
     *
     * @param string|null $written Already validated by SitemapConfig.
     * @param string|null $file
     * @return string|null
     */
    public static function lastmod(?string $written, ?string $file): ?string
    {
        if ($written !== null) {
            return $written;
        }

        if ($file === null || !is_file($file)) {
            return null;
        }

        $mtime = filemtime($file);

        return $mtime === false ? null : gmdate(DATE_W3C, $mtime);
    }

    /**
     * One page of $items, 1-based, $max per page.
     *
     * @template T
     * @param list<T> $items
     * @param int $pageNum
     * @param int $max
     * @return list<T>
     */
    public static function page(array $items, int $pageNum, int $max): array
    {
        if ($pageNum < 1 || $max < 1) {
            return [];
        }

        return array_slice($items, ($pageNum - 1) * $max, $max);
    }

    /**
     * @param int $count
     * @param int $max
     * @return int
     */
    public static function pageCount(int $count, int $max): int
    {
        return $count > 0 && $max > 0 ? (int) ceil($count / $max) : 0;
    }
}
