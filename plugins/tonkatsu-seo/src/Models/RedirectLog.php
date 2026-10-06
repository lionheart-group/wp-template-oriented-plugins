<?php

namespace TonkatsuPlugin\Models;

use TonkatsuPlugin\Consts;
use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Structure\RedirectConfig;

/**
 * The redirect log: a count per rule and one row per request.
 *
 * Written only while `SiteConfig::$logRedirects` is on. This is data about
 * what happened, never configuration: the redirects themselves stay in theme
 * code. No IP address or user agent is stored.
 */
class RedirectLog
{
    /**
     * Count and last hit per rule.
     */
    public const STATS_TABLE = 'tonkatsu_redirect_stats';

    /**
     * One row per redirect (or 410) answered.
     */
    public const LOG_TABLE = 'tonkatsu_redirect_log';

    /**
     * Option holding the schema version the tables were last brought up to.
     */
    public const VERSION_OPTION = 'tonkatsu_db_version';

    /**
     * Old rows are purged on one write in this many.
     */
    public const PURGE_ODDS = 100;

    /**
     * User agent fragments that mark a bot (case-insensitive).
     *
     * @var string[]
     */
    public const BOT_PATTERNS = ['bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'mediapartners', 'headless'];

    /**
     * @return string
     */
    public static function statsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::STATS_TABLE;
    }

    /**
     * @return string
     */
    public static function logTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::LOG_TABLE;
    }

    /**
     * The tables in their current form, for dbDelta().
     *
     * dbDelta() is particular: one column per line, two spaces after
     * `PRIMARY KEY`, `KEY name (column)` for indexes.
     *
     * @return list<string>
     */
    public static function schema(): array
    {
        global $wpdb;
        $collate = $wpdb->get_charset_collate();

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $collate is core's get_charset_collate(), which cannot be a placeholder
        $schema = [
            $wpdb->prepare(
                "CREATE TABLE %i (
rule_key char(32) NOT NULL,
type varchar(10) NOT NULL,
source text NOT NULL,
hits bigint(20) unsigned NOT NULL DEFAULT 0,
last_hit_at datetime NOT NULL,
PRIMARY KEY  (rule_key)
) " . $collate . ';',
                self::statsTable()
            ),
            $wpdb->prepare(
                "CREATE TABLE %i (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
rule_key char(32) NOT NULL,
requested text NOT NULL,
location text NULL,
status smallint(5) unsigned NOT NULL,
referrer text NOT NULL,
is_bot tinyint(1) NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY rule_key (rule_key),
KEY created_at (created_at)
) " . $collate . ';',
                self::logTable()
            ),
        ];
        // phpcs:enable

        return $schema;
    }

    /**
     * Create or update the tables once per schema version, while logging is on.
     *
     * Hooked on `init` after the theme has registered SiteConfig. The stored
     * version is one autoloaded option, so a request where it matches costs
     * no query. Runs on any request, like TOFU's migrations: an update made by
     * replacing the files never fires an activation or upgrade hook.
     *
     * @param ?callable(): bool $installer Creates the tables; install() by default.
     * @return bool Whether the installer ran.
     */
    public static function maybeInstall(?callable $installer = null): bool
    {
        if (!Seo::getSite()->logRedirects || !self::needsInstall(get_option(self::VERSION_OPTION))) {
            return false;
        }

        $installer ??= [static::class, 'install'];
        if ($installer() === true) {
            update_option(self::VERSION_OPTION, Consts::DB_VERSION, true);
        }

        return true;
    }

    /**
     * Whether the stored version calls for creating or updating the tables.
     *
     * @param mixed $stored get_option() result (false when missing).
     * @return bool
     */
    public static function needsInstall(mixed $stored): bool
    {
        return $stored !== Consts::DB_VERSION;
    }

    /**
     * Whether the tables are in place for this version of the code.
     *
     * @return bool
     */
    public static function isReady(): bool
    {
        return !self::needsInstall(get_option(self::VERSION_OPTION));
    }

    /**
     * Run dbDelta() on the schema.
     *
     * @return bool Whether both tables exist afterwards.
     */
    public static function install(): bool
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta(self::schema());

        foreach ([self::statsTable(), self::logTable()] as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
                return false;
            }
        }

        return true;
    }

    /**
     * Record one redirect (or 410).
     *
     * Does nothing while logging is off or the tables are not ready (their
     * creation failed, or has not run yet): a redirect must never fail
     * because its log could not be written.
     *
     * @param RedirectConfig $config The rule that matched.
     * @param string $requested The request URI (path and query string).
     * @param ?string $location Where the visitor was sent; null for 410.
     * @param string $referrer The Referer header, as received.
     * @param string $userAgent Only used to tell bots apart; not stored.
     * @return bool Whether anything was written.
     */
    public static function record(RedirectConfig $config, string $requested, ?string $location, string $referrer, string $userAgent): bool
    {
        $site = Seo::getSite();
        if (!$site->logRedirects || !self::isReady()) {
            return false;
        }

        global $wpdb;
        $key = self::ruleKey($config);
        $now = gmdate('Y-m-d H:i:s');

        $wpdb->query($wpdb->prepare(
            'INSERT INTO %i (rule_key, type, source, hits, last_hit_at) VALUES (%s, %s, %s, 1, %s)
            ON DUPLICATE KEY UPDATE hits = hits + 1, last_hit_at = %s',
            self::statsTable(),
            $key,
            $config->type,
            $config->path,
            $now,
            $now
        ));

        $wpdb->insert(
            self::logTable(),
            [
                'rule_key'   => $key,
                'requested'  => $requested,
                'location'   => $location,
                'status'     => $config->status,
                'referrer'   => self::stripReferrer($referrer),
                'is_bot'     => self::isBot($userAgent) ? 1 : 0,
                'created_at' => $now,
            ],
            ['%s', '%s', '%s', '%d', '%s', '%d', '%s']
        );

        self::maybePurge($site->redirectLogDays, random_int(1, self::PURGE_ODDS));

        return true;
    }

    /**
     * Delete rows older than the retention period, on one roll in PURGE_ODDS.
     *
     * The counts per rule are never deleted.
     *
     * @param int $days SiteConfig::$redirectLogDays.
     * @param int $roll 1…PURGE_ODDS; purges on 1.
     * @return bool Whether the rows were purged.
     */
    public static function maybePurge(int $days, int $roll): bool
    {
        if ($roll !== 1) {
            return false;
        }

        global $wpdb;
        $wpdb->query($wpdb->prepare(
            'DELETE FROM %i WHERE created_at < %s',
            self::logTable(),
            gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS)
        ));

        return true;
    }

    /**
     * The key a rule is counted under: its type and normalized source.
     *
     * The same as Seo's registry key, so it survives a change of target or
     * status and stays unique per rule.
     *
     * @param RedirectConfig $config
     * @return string 32 hex characters.
     */
    public static function ruleKey(RedirectConfig $config): string
    {
        return md5($config->type . ' ' . $config->path);
    }

    /**
     * Whether a user agent looks like a bot. An empty one counts as a bot.
     *
     * @param string $userAgent
     * @return bool
     */
    public static function isBot(string $userAgent): bool
    {
        $userAgent = strtolower(trim($userAgent));
        if ($userAgent === '') {
            return true;
        }

        foreach (self::BOT_PATTERNS as $pattern) {
            if (str_contains($userAgent, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The referrer without its query string and fragment, which may carry
     * personal data (search terms, tokens). '' for anything but http(s).
     *
     * @param string $referrer
     * @return string
     */
    public static function stripReferrer(string $referrer): string
    {
        $referrer = trim($referrer);
        $referrer = substr($referrer, 0, strcspn($referrer, '?#'));

        if (preg_match('#^https?://[^/\s]+#i', $referrer) !== 1) {
            return '';
        }

        return $referrer;
    }

    /**
     * Counts per rule, keyed by rule key. Empty when the tables are not ready.
     *
     * @return array<string, \stdClass> Rows with rule_key, type, source, hits and last_hit_at.
     */
    public static function getStats(): array
    {
        if (!self::isReady()) {
            return [];
        }

        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT rule_key, type, source, hits, last_hit_at FROM %i ORDER BY last_hit_at DESC',
            self::statsTable()
        ));

        $stats = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $stats[(string) $row->rule_key] = $row;
        }

        return $stats;
    }

    /**
     * Rows newest first, optionally for one rule.
     *
     * @param ?string $ruleKey
     * @param int $perPage
     * @param int $page 1-based.
     * @return array{items: list<\stdClass>, total: int}
     */
    public static function getEntries(?string $ruleKey, int $perPage, int $page): array
    {
        if (!self::isReady()) {
            return ['items' => [], 'total' => 0];
        }

        global $wpdb;
        $table = self::logTable();
        $offset = max(0, ($page - 1) * $perPage);

        if ($ruleKey !== null) {
            $items = $wpdb->get_results($wpdb->prepare(
                'SELECT * FROM %i WHERE rule_key = %s ORDER BY id DESC LIMIT %d OFFSET %d',
                $table,
                $ruleKey,
                $perPage,
                $offset
            ));
            $total = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE rule_key = %s', $table, $ruleKey));
        } else {
            $items = $wpdb->get_results($wpdb->prepare(
                'SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d',
                $table,
                $perPage,
                $offset
            ));
            $total = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $table));
        }

        return ['items' => is_array($items) ? array_values($items) : [], 'total' => (int) $total];
    }
}
