<?php

namespace TofuPlugin\Tests\Unit\Helpers;

use TofuPlugin\Consts;
use TofuPlugin\Helpers\Session;
use TofuPlugin\Tests\Unit\BaseTestCase;

/**
 * Pins WHEN a session cookie is issued.
 *
 * Reading a session used to issue one, and Form::register() reads on every
 * `init` — so every page of the site, the admin included, answered with a
 * Set-Cookie even when it had no form on it. Beyond being surprising, that
 * is enough to stop most full-page caches (Varnish, nginx fastcgi_cache,
 * WP Rocket) from serving anything cached.
 *
 * The rule these tests hold the code to: a cookie is issued when session
 * data is SAVED, and at no other time.
 */
class SessionCookieTest extends BaseTestCase
{
    /**
     * BaseTestCase resets $__tofu_setcookie_calls, Session::$corsMode and
     * Session::$cookieIssued between every test; only $_COOKIE is specific
     * to this file.
     */
    protected function setUp(): void
    {
        parent::setUp();
        unset($_COOKIE[Consts::SESSION_COOKIE_KEY]);
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[Consts::SESSION_COOKIE_KEY]);
        parent::tearDown();
    }

    private function setCorsMode(bool $enabled): void
    {
        $property = new \ReflectionProperty(Session::class, 'corsMode');
        $property->setAccessible(true);
        $property->setValue(null, $enabled);
    }

    /**
     * @return array<int, array{name: string, value: string, options: array<string, mixed>|int}>
     */
    private function issuedCookies(): array
    {
        return array_values(array_filter(
            $GLOBALS['__tofu_setcookie_calls'],
            static fn (array $call): bool => $call['name'] === Consts::SESSION_COOKIE_KEY
        ));
    }

    // -----------------------------------------------------------------
    // Reading never issues
    // -----------------------------------------------------------------

    public function testReadingASessionWithoutACookieIssuesNothing(): void
    {
        $this->assertNull(Session::get('contact'));
        $this->assertSame([], $this->issuedCookies(), 'Reading a session must not send a cookie.');
    }

    public function testReadingASessionWithACookieDoesNotReissueIt(): void
    {
        $_COOKIE[Consts::SESSION_COOKIE_KEY] = 'existing-session-key';

        Session::get('contact');

        $this->assertSame([], $this->issuedCookies(), 'A read must not refresh the cookie.');
    }

    public function testClearingWithoutACookieIssuesNothing(): void
    {
        Session::clear('contact');

        $this->assertSame([], $this->issuedCookies(), 'Clearing nothing must not hand out a key.');
    }

    // -----------------------------------------------------------------
    // Saving issues exactly once
    // -----------------------------------------------------------------

    public function testSavingWithoutACookieIssuesOne(): void
    {
        Session::save('contact', ['values' => ['name' => 'Taro']]);

        $issued = $this->issuedCookies();
        $this->assertCount(1, $issued);
        $this->assertNotSame('', $issued[0]['value'], 'A freshly minted key must not be empty.');
    }

    public function testSavingWithACookieKeepsTheSameKey(): void
    {
        $_COOKIE[Consts::SESSION_COOKIE_KEY] = 'existing-session-key';

        Session::save('contact', ['values' => []]);

        $issued = $this->issuedCookies();
        $this->assertCount(1, $issued);
        $this->assertSame(
            'existing-session-key',
            $issued[0]['value'],
            'Saving must refresh the existing key, never rotate it — rotating would orphan the stored row.'
        );
    }

    // -----------------------------------------------------------------
    // Cookie attributes
    // -----------------------------------------------------------------

    public function testIssuedCookieIsHttpOnlyAndLaxByDefault(): void
    {
        Session::save('contact', ['values' => []]);

        $options = $this->issuedCookies()[0]['options'];
        $this->assertIsArray($options);
        $this->assertTrue($options['httponly']);
        $this->assertSame('Lax', $options['samesite']);
        $this->assertArrayHasKey('expires', $options);
    }

    /**
     * Cross-origin AJAX sends the cookie only when it is SameSite=None, and
     * browsers reject SameSite=None unless it is also Secure.
     */
    public function testCorsModeIssuesTheCookieAsSameSiteNoneAndSecure(): void
    {
        Session::enableCors();

        Session::save('contact', ['values' => []]);

        $options = $this->issuedCookies()[0]['options'];
        $this->assertIsArray($options);
        $this->assertSame('None', $options['samesite']);
        $this->assertTrue($options['secure']);
    }

    // -----------------------------------------------------------------
    // At most one Set-Cookie header per request
    // -----------------------------------------------------------------

    /**
     * A single response can call save() more than once — e.g. a no-confirm-
     * step submission runs processInput() then processConfirm() inline, or a
     * page embeds several dynamically-templated forms. Each call would
     * otherwise carry the same value in its own Set-Cookie header.
     */
    public function testSavingTwiceInOneRequestIssuesOnlyOneCookie(): void
    {
        Session::save('contact', ['values' => ['name' => 'Taro']]);
        Session::save('newsletter', ['values' => ['email' => 'taro@example.com']]);

        $this->assertCount(1, $this->issuedCookies());
    }

    public function testSavingTwiceInOneRequestStillPersistsBothRows(): void
    {
        // The cookie-issuance guard must short-circuit setcookie() only —
        // never the DB write. Spy on $wpdb to prove both saves reach it.
        $originalWpdb = $GLOBALS['wpdb'];
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public $insert_id = 0;
            public array $inserted = [];
            public function insert($table, $data, $format = null)
            {
                $this->insert_id++;
                $this->inserted[] = $data;
                return 1;
            }
            public function update($table, $data, $where, $format = null, $where_format = null)
            {
                return 1;
            }
            public function prepare($query, ...$args)
            {
                return $query;
            }
            public function get_var($query, $x = 0, $y = 0)
            {
                return null; // exists() sees no prior row, so save() always inserts.
            }
            public function get_results($query, $output = OBJECT)
            {
                return [];
            }
            public function query($query)
            {
                return true;
            }
        };

        try {
            Session::save('contact', ['values' => ['name' => 'Taro']]);
            Session::save('newsletter', ['values' => ['email' => 'taro@example.com']]);

            $this->assertCount(
                2,
                $GLOBALS['wpdb']->inserted,
                'both saves must reach the database even though only one issues a cookie'
            );
        } finally {
            $GLOBALS['wpdb'] = $originalWpdb;
        }
    }
}
