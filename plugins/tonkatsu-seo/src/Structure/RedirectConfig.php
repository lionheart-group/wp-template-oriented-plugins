<?php

namespace TonkatsuPlugin\Structure;

use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Helpers\Url;

/**
 * Redirect configuration class.
 *
 * One redirect (or one "gone" path). Register with `Seo::registerRedirect()`
 * or `Seo::registerRedirects()`.
 *
 * `from` is a path relative to the home URL, like the paths of
 * `Seo::registerPage()`. Exact and prefix sources are normalized the same way
 * (slashes, query strings and percent-encoding do not matter); a regex source
 * is a pattern matched against that normalized path.
 *
 * @package TonkatsuPlugin\Structure
 */
class RedirectConfig
{
    /**
     * `from` is one path.
     */
    public const TYPE_EXACT = 'exact';

    /**
     * `from` is a path and everything below it; the rest is carried over.
     */
    public const TYPE_PREFIX = 'prefix';

    /**
     * `from` is a regular expression; `$1`… in `to` are its groups.
     */
    public const TYPE_REGEX = 'regex';

    /**
     * @var string[]
     */
    public const TYPES = [self::TYPE_EXACT, self::TYPE_PREFIX, self::TYPE_REGEX];

    /**
     * Status codes accepted for `status`. 410 answers "Gone" instead of redirecting.
     *
     * @var int[]
     */
    public const STATUSES = [301, 302, 307, 308, 410];

    /**
     * Keys accepted by fromArray(), mapped to their expected type.
     *
     * @var array<string, string>
     */
    private const FIELDS = [
        'from'   => 'string',
        'to'     => '?string',
        'status' => 'int',
        'type'   => 'string',
    ];

    /**
     * The normalized source path (exact and prefix), or the pattern as given (regex).
     *
     * '' is the front page.
     *
     * @var string
     */
    public readonly string $path;

    public function __construct(
        /**
         * Source: a path relative to the home URL, or a regex when `type` is `regex`.
         *
         * @var string
         */
        public readonly string $from,

        /**
         * Target: an absolute http(s) URL, or a path starting with `/`, which is
         * relative to the home URL (like `from`). Must be null for 410.
         *
         * @var ?string
         */
        public readonly ?string $to = null,

        /**
         * 301, 302, 307, 308, or 410 (Gone).
         *
         * @var int
         */
        public readonly int $status = 301,

        /**
         * One of the TYPE_* constants.
         *
         * @var string
         */
        public readonly string $type = self::TYPE_EXACT,
    )
    {
        $label = sprintf("RedirectConfig '%s'", $this->from);

        if (!in_array($this->type, self::TYPES, true)) {
            throw new \InvalidArgumentException(sprintf(
                '%s: unknown type "%s". Allowed types: %s.',
                esc_html($label),
                esc_html($this->type),
                esc_html(implode(', ', self::TYPES))
            ));
        }

        if (!in_array($this->status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf(
                '%s: status %d is not supported. Allowed statuses: %s.',
                esc_html($label),
                (int) $this->status,
                esc_html(implode(', ', self::STATUSES))
            ));
        }

        if ($this->status === 410) {
            if ($this->to !== null) {
                throw new \InvalidArgumentException(sprintf('%s: a 410 (Gone) has no "to".', esc_html($label)));
            }
        } elseif ($this->to === null || !Url::isValid($this->to)) {
            throw new \InvalidArgumentException(sprintf(
                "%s: to '%s' must be an absolute http(s) URL or a path starting with '/'.",
                esc_html($label),
                esc_html((string) $this->to)
            ));
        }

        if ($this->type === self::TYPE_REGEX) {
            $this->path = $this->from;
            $this->assertPattern($label);
            return;
        }

        $this->path = Seo::normalizePath($this->from);

        // An empty prefix would catch every URL of the site; say so with a regex instead.
        if ($this->type === self::TYPE_PREFIX && $this->path === '') {
            throw new \InvalidArgumentException(sprintf(
                "%s: a prefix redirect cannot start at the front page. Use type 'regex' to redirect every URL.",
                esc_html($label)
            ));
        }

        $this->assertNoLoop($label);
    }

    /**
     * The pattern given to preg_match(): `from` wrapped in `~` delimiters, with the `u` flag.
     *
     * @return string
     */
    public function pattern(): string
    {
        return '~' . preg_replace('/(?<!\\\\)~/', '\\~', $this->from) . '~u';
    }

    /**
     * Build from an array whose keys are the constructor's parameter names.
     *
     * <code>
     * // redirects.php
     * return [
     *     ['from' => '/old-page/', 'to' => '/new-page/'],
     *     ['from' => '/closed/', 'status' => 410],
     * ];
     * </code>
     *
     * Unknown keys and wrongly-typed values throw, so a typo such as
     * `'code' => 302` fails on `init` instead of silently redirecting with 301.
     *
     * @param array<array-key, mixed> $values
     * @param string $label Included in error messages, e.g. 'redirect #3'.
     * @return self
     */
    public static function fromArray(array $values, string $label = ''): self
    {
        $prefix = $label !== '' ? "RedirectConfig '{$label}'" : 'RedirectConfig';

        $unknown = array_diff(array_map('strval', array_keys($values)), array_keys(self::FIELDS));
        if ($unknown !== []) {
            throw new \InvalidArgumentException(sprintf(
                '%s: unknown key(s) "%s". Allowed keys: %s.',
                esc_html($prefix),
                esc_html(implode('", "', $unknown)),
                esc_html(implode(', ', array_keys(self::FIELDS)))
            ));
        }

        if (!array_key_exists('from', $values)) {
            throw new \InvalidArgumentException(sprintf('%s: "from" is required.', esc_html($prefix)));
        }

        foreach ($values as $key => $value) {
            $expected = self::FIELDS[$key];
            $valid = match ($expected) {
                'int'     => is_int($value),
                '?string' => $value === null || is_string($value),
                default   => is_string($value),
            };

            if (!$valid) {
                throw new \InvalidArgumentException(sprintf(
                    '%s: "%s" must be %s, got %s.',
                    esc_html($prefix),
                    esc_html((string) $key),
                    match ($expected) {
                        'int'     => 'an int',
                        '?string' => 'a string or null',
                        default   => 'a string',
                    },
                    esc_html(get_debug_type($value))
                ));
            }
        }

        return new self(...$values);
    }

    /**
     * @param string $label
     */
    private function assertPattern(string $label): void
    {
        if (trim($this->from) === '') {
            throw new \InvalidArgumentException(sprintf('%s: a regex redirect needs a pattern.', esc_html($label)));
        }

        // preg_match() warns and returns false on an invalid pattern. The
        // warning names the problem ("missing closing parenthesis at offset 5");
        // preg_last_error_msg() only says "Internal error" for compile errors.
        error_clear_last();
        if (@preg_match($this->pattern(), '') === false) {
            $error = error_get_last();
            $message = $error !== null
                ? (string) preg_replace('/^preg_match\(\):\s*/', '', $error['message'])
                : preg_last_error_msg();

            throw new \InvalidArgumentException(sprintf(
                '%s: invalid regex (%s).',
                esc_html($label),
                esc_html($message)
            ));
        }
    }

    /**
     * Reject a same-site target that would send the visitor straight back.
     *
     * Only root-relative targets are checked; an absolute URL cannot be
     * compared with the home URL without WordPress.
     *
     * @param string $label
     */
    private function assertNoLoop(string $label): void
    {
        if ($this->to === null || !Url::isRootRelative($this->to)) {
            return;
        }

        $target = Seo::normalizePath($this->to);
        $loops = $this->type === self::TYPE_EXACT
            ? $target === $this->path
            // /a/ → /a/b/ would grow on every hop: /a/x → /a/b/x → /a/b/b/x …
            : ($target === $this->path || str_starts_with($target, $this->path . '/'));

        if ($loops) {
            throw new \InvalidArgumentException(sprintf(
                "%s: to '%s' redirects back to itself.",
                esc_html($label),
                esc_html($this->to)
            ));
        }
    }
}
