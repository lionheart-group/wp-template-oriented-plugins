<?php

namespace TonkatsuPlugin\Tests\Unit\Models;

use TonkatsuPlugin\Consts;
use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Models\RedirectLog;
use TonkatsuPlugin\Structure\RedirectConfig;
use TonkatsuPlugin\Structure\SiteConfig;
use TonkatsuPlugin\Tests\Unit\BaseTestCase;

class RedirectLogTest extends BaseTestCase
{
    private const BROWSER = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36';

    private function wpdb(): \TonkatsuTestWpdb
    {
        return $GLOBALS['wpdb'];
    }

    private function enableLogging(bool $tablesReady = true): void
    {
        Seo::setSite(new SiteConfig(logRedirects: true));

        if ($tablesReady) {
            $GLOBALS['__tonkatsu_test_options'][RedirectLog::VERSION_OPTION] = Consts::DB_VERSION;
        }
    }

    public function testRuleKeyIsStablePerTypeAndSource(): void
    {
        $a = new RedirectConfig(from: '/old-page/', to: '/new-page/');
        $b = new RedirectConfig(from: 'old-page', to: '/other/', status: 302);
        $prefix = new RedirectConfig(from: '/old-page/', to: '/new-page/', type: RedirectConfig::TYPE_PREFIX);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', RedirectLog::ruleKey($a));
        $this->assertSame(RedirectLog::ruleKey($a), RedirectLog::ruleKey($b));
        $this->assertNotSame(RedirectLog::ruleKey($a), RedirectLog::ruleKey($prefix));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function userAgents(): array
    {
        return [
            'browser'     => [self::BROWSER, false],
            'googlebot'   => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', true],
            'bingbot'     => ['Mozilla/5.0 (compatible; bingbot/2.0)', true],
            'yahoo slurp' => ['Mozilla/5.0 (compatible; Yahoo! Slurp)', true],
            'crawler'     => ['SomeCrawler/1.0', true],
            'spider'      => ['Baiduspider', true],
            'facebook'    => ['facebookexternalhit/1.1', true],
            'headless'    => ['Mozilla/5.0 HeadlessChrome/120.0', true],
            'empty'       => ['', true],
        ];
    }

    /**
     * @dataProvider userAgents
     */
    public function testIsBot(string $userAgent, bool $expected): void
    {
        $this->assertSame($expected, RedirectLog::isBot($userAgent));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function referrers(): array
    {
        return [
            'query'          => ['https://example.com/page?q=1', 'https://example.com/page'],
            'fragment'       => ['https://example.com/page#top', 'https://example.com/page'],
            'both'           => ['https://example.com/page?q=1#top', 'https://example.com/page'],
            'plain'          => ['http://example.com/', 'http://example.com/'],
            'empty'          => ['', ''],
            'not http'       => ['javascript:alert(1)', ''],
            'root-relative'  => ['/page', ''],
        ];
    }

    /**
     * @dataProvider referrers
     */
    public function testStripReferrer(string $referrer, string $expected): void
    {
        $this->assertSame($expected, RedirectLog::stripReferrer($referrer));
    }

    public function testRecordWritesTheCountAndTheRow(): void
    {
        $this->enableLogging();
        $config = new RedirectConfig(from: '/old-page/', to: '/new-page/');

        $written = RedirectLog::record($config, '/old-page/?a=1', 'https://example.com/new-page/?a=1', 'https://example.org/from?q=secret', self::BROWSER);

        $this->assertTrue($written);

        $upsert = $this->wpdb()->queries[0] ?? '';
        $this->assertStringContainsString('INSERT INTO `wp_tonkatsu_redirect_stats`', $upsert);
        $this->assertStringContainsString("'" . RedirectLog::ruleKey($config) . "', 'exact', 'old-page', 1", $upsert);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE hits = hits + 1', $upsert);

        $this->assertCount(1, $this->wpdb()->inserts);
        [$table, $row] = $this->wpdb()->inserts[0];
        $this->assertSame('wp_tonkatsu_redirect_log', $table);
        $this->assertSame(RedirectLog::ruleKey($config), $row['rule_key']);
        $this->assertSame('/old-page/?a=1', $row['requested']);
        $this->assertSame('https://example.com/new-page/?a=1', $row['location']);
        $this->assertSame(301, $row['status']);
        $this->assertSame('https://example.org/from', $row['referrer']);
        $this->assertSame(0, $row['is_bot']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $row['created_at']);
        $this->assertArrayNotHasKey('user_agent', $row);
    }

    public function testRecordKeepsNoLocationForGone(): void
    {
        $this->enableLogging();

        RedirectLog::record(new RedirectConfig(from: '/closed/', status: 410), '/closed/', null, '', 'Googlebot');

        $row = $this->wpdb()->inserts[0][1] ?? [];
        $this->assertArrayHasKey('location', $row);
        $this->assertNull($row['location']);
        $this->assertSame(410, $row['status'] ?? null);
        $this->assertSame(1, $row['is_bot'] ?? null);
    }

    public function testRecordWritesNothingWhileLoggingIsOff(): void
    {
        Seo::setSite(new SiteConfig());
        $GLOBALS['__tonkatsu_test_options'][RedirectLog::VERSION_OPTION] = Consts::DB_VERSION;

        $this->assertFalse(RedirectLog::record(new RedirectConfig(from: '/a/', to: '/b/'), '/a/', 'https://example.com/b/', '', self::BROWSER));
        $this->assertSame([], $this->wpdb()->queries);
        $this->assertSame([], $this->wpdb()->inserts);
    }

    public function testRecordWritesNothingBeforeTheTablesAreReady(): void
    {
        $this->enableLogging(tablesReady: false);

        $this->assertFalse(RedirectLog::record(new RedirectConfig(from: '/a/', to: '/b/'), '/a/', 'https://example.com/b/', '', self::BROWSER));
        $this->assertSame([], $this->wpdb()->queries);
        $this->assertSame([], $this->wpdb()->inserts);

        // Tables from an older schema version are not written to either
        $GLOBALS['__tonkatsu_test_options'][RedirectLog::VERSION_OPTION] = '0';
        $this->assertFalse(RedirectLog::record(new RedirectConfig(from: '/a/', to: '/b/'), '/a/', 'https://example.com/b/', '', self::BROWSER));
    }

    public function testPurgeDeletesOnlyOldRowsOnOneRollInTheOdds(): void
    {
        $this->assertFalse(RedirectLog::maybePurge(90, 2));
        $this->assertSame([], $this->wpdb()->queries);

        $this->assertTrue(RedirectLog::maybePurge(90, 1));
        $this->assertCount(1, $this->wpdb()->queries);
        $this->assertMatchesRegularExpression(
            "/^DELETE FROM `wp_tonkatsu_redirect_log` WHERE created_at < '(.+)'$/",
            $this->wpdb()->queries[0]
        );

        preg_match("/'(.+)'/", $this->wpdb()->queries[0], $m);
        $this->assertEqualsWithDelta(time() - 90 * 86400, strtotime($m[1] . ' UTC'), 5);
    }

    public function testMaybeInstallRunsOnlyWhileLoggingIsOn(): void
    {
        $runs = 0;
        $installer = function () use (&$runs): bool {
            $runs++;
            return true;
        };

        Seo::setSite(new SiteConfig());
        $this->assertFalse(RedirectLog::maybeInstall($installer));
        $this->assertSame(0, $runs);
        $this->assertArrayNotHasKey(RedirectLog::VERSION_OPTION, $GLOBALS['__tonkatsu_test_options'] ?? []);
    }

    public function testMaybeInstallRunsOncePerSchemaVersion(): void
    {
        $this->enableLogging(tablesReady: false);
        $runs = 0;
        $installer = function () use (&$runs): bool {
            $runs++;
            return true;
        };

        $this->assertTrue(RedirectLog::maybeInstall($installer));
        $this->assertSame(Consts::DB_VERSION, $GLOBALS['__tonkatsu_test_options'][RedirectLog::VERSION_OPTION] ?? null);
        $this->assertTrue(RedirectLog::isReady());

        $this->assertFalse(RedirectLog::maybeInstall($installer));
        $this->assertSame(1, $runs);
    }

    public function testMaybeInstallKeepsTheVersionUnsetWhenCreationFails(): void
    {
        $this->enableLogging(tablesReady: false);

        $this->assertTrue(RedirectLog::maybeInstall(fn (): bool => false));
        $this->assertFalse(RedirectLog::isReady());

        // Tried again on the next request
        $this->assertTrue(RedirectLog::maybeInstall(fn (): bool => true));
        $this->assertTrue(RedirectLog::isReady());
    }

    public function testNeedsInstallWhenTheStoredVersionDiffers(): void
    {
        $this->assertTrue(RedirectLog::needsInstall(false));
        $this->assertTrue(RedirectLog::needsInstall('0'));
        $this->assertFalse(RedirectLog::needsInstall(Consts::DB_VERSION));
    }

    public function testSchemaIsWrittenForDbDelta(): void
    {
        [$stats, $log] = RedirectLog::schema();

        $this->assertStringStartsWith('CREATE TABLE `wp_tonkatsu_redirect_stats` (', $stats);
        $this->assertStringContainsString("PRIMARY KEY  (rule_key)", $stats);
        $this->assertStringContainsString("hits bigint(20) unsigned NOT NULL DEFAULT 0,\n", $stats);

        $this->assertStringStartsWith('CREATE TABLE `wp_tonkatsu_redirect_log` (', $log);
        $this->assertStringContainsString("PRIMARY KEY  (id),\nKEY rule_key (rule_key),\nKEY created_at (created_at)\n", $log);

        // Columns, in order: no IP address, no user agent
        $columns = static fn (string $sql): array => array_values(array_filter(array_map(
            static fn (string $line): string => explode(' ', $line)[0],
            array_slice(explode("\n", $sql), 1, -1)
        ), static fn (string $name): bool => !in_array($name, ['PRIMARY', 'KEY'], true)));

        $this->assertSame(['rule_key', 'type', 'source', 'hits', 'last_hit_at'], $columns($stats));
        $this->assertSame(['id', 'rule_key', 'requested', 'location', 'status', 'referrer', 'is_bot', 'created_at'], $columns($log));
    }

    public function testReadsReturnNothingBeforeTheTablesAreReady(): void
    {
        $this->assertSame([], RedirectLog::getStats());
        $this->assertSame(['items' => [], 'total' => 0], RedirectLog::getEntries(null, 25, 1));
        $this->assertSame([], $this->wpdb()->queries);
    }

    public function testEntriesAreFilteredByRuleAndPaged(): void
    {
        $GLOBALS['__tonkatsu_test_options'][RedirectLog::VERSION_OPTION] = Consts::DB_VERSION;
        $key = str_repeat('a', 32);

        RedirectLog::getEntries($key, 25, 3);

        $this->assertSame(
            "SELECT * FROM `wp_tonkatsu_redirect_log` WHERE rule_key = '{$key}' ORDER BY id DESC LIMIT 25 OFFSET 50",
            $this->wpdb()->queries[0]
        );
    }
}
