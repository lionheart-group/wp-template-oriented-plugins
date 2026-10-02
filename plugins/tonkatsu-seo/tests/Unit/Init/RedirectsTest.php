<?php

namespace TonkatsuPlugin\Tests\Unit\Init;

use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Init\Redirects;
use TonkatsuPlugin\Models\Context;
use TonkatsuPlugin\Structure\PageConfig;
use TonkatsuPlugin\Structure\RedirectConfig;
use TonkatsuPlugin\Tests\Unit\BaseTestCase;

class RedirectsTest extends BaseTestCase
{
    private const HOME = 'https://example.com/';

    /**
     * @param list<RedirectConfig> $redirects
     * @return ?array{status: int, location: ?string}
     */
    private function match(array $redirects, string $requestUri, string $home = self::HOME): ?array
    {
        $query = parse_url($requestUri, PHP_URL_QUERY);

        return Redirects::match($redirects, Context::relativePath($requestUri, $home), is_string($query) ? $query : '', $home);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function oldPageSpellings(): array
    {
        return [
            'with slash'      => ['/old-page/'],
            'without slash'   => ['/old-page'],
            'double slashes'  => ['//old-page//'],
        ];
    }

    /**
     * @dataProvider oldPageSpellings
     */
    public function testExactMatchesAnySpellingOfThePath(string $uri): void
    {
        $match = $this->match([new RedirectConfig(from: '/old-page/', to: '/new-page/')], $uri);

        $this->assertSame(['status' => 301, 'location' => 'https://example.com/new-page/'], $match);
    }

    public function testExactDoesNotMatchBelowTheSource(): void
    {
        $this->assertNull($this->match([new RedirectConfig(from: '/old-page/', to: '/new-page/')], '/old-page/child/'));
    }

    public function testJapanesePathMatchesEncodedAndDecodedRequests(): void
    {
        $redirects = [new RedirectConfig(from: '/旧ページ/', to: '/新ページ/')];

        $encoded = $this->match($redirects, '/%E6%97%A7%E3%83%9A%E3%83%BC%E3%82%B8/');
        $this->assertSame('https://example.com/新ページ/', $encoded['location'] ?? null);

        $this->assertNotNull($this->match($redirects, '/旧ページ/'));
    }

    public function testFrontPageCanBeRedirected(): void
    {
        $match = $this->match([new RedirectConfig(from: '/', to: 'https://example.org/')], '/');

        $this->assertSame('https://example.org/', $match['location'] ?? null);
    }

    public function testNoMatchIsNull(): void
    {
        $this->assertNull($this->match([new RedirectConfig(from: '/old-page/', to: '/new-page/')], '/other/'));
        $this->assertNull($this->match([], '/old-page/'));
    }

    public function testStatusIsPassedThrough(): void
    {
        $match = $this->match([new RedirectConfig(from: '/campaign/', to: 'https://example.org/', status: 302)], '/campaign/');

        $this->assertSame(['status' => 302, 'location' => 'https://example.org/'], $match);
    }

    public function testGoneHasNoLocation(): void
    {
        $match = $this->match([new RedirectConfig(from: '/closed/', status: 410)], '/closed/?a=1');

        $this->assertSame(['status' => 410, 'location' => null], $match);
    }

    public function testPrefixCarriesTheRestOfThePathOver(): void
    {
        $redirects = [new RedirectConfig(from: '/old-dir/', to: '/new-dir/', type: RedirectConfig::TYPE_PREFIX)];

        $this->assertSame('https://example.com/new-dir/', $this->match($redirects, '/old-dir/')['location'] ?? null);
        $this->assertSame('https://example.com/new-dir/a/b/', $this->match($redirects, '/old-dir/a/b/')['location'] ?? null);
        $this->assertSame('https://example.com/new-dir/file.pdf', $this->match($redirects, '/old-dir/file.pdf')['location'] ?? null);
        $this->assertNull($this->match($redirects, '/old-directory/'));
    }

    public function testPrefixTrailingSlashFollowsTheTarget(): void
    {
        $redirects = [new RedirectConfig(from: '/old-dir/', to: '/new-dir', type: RedirectConfig::TYPE_PREFIX)];

        $this->assertSame('https://example.com/new-dir/a', $this->match($redirects, '/old-dir/a/')['location'] ?? null);
    }

    public function testPrefixRemainderIsEncodedPerSegment(): void
    {
        $redirects = [new RedirectConfig(from: '/old-dir/', to: 'https://example.org/new/', type: RedirectConfig::TYPE_PREFIX)];

        $this->assertSame(
            'https://example.org/new/%E4%BC%9A%E7%A4%BE/a%20b/',
            $this->match($redirects, '/old-dir/%E4%BC%9A%E7%A4%BE/a%20b/')['location'] ?? null
        );
    }

    public function testLongestPrefixWins(): void
    {
        $redirects = [
            new RedirectConfig(from: '/docs/', to: '/manual/', type: RedirectConfig::TYPE_PREFIX),
            new RedirectConfig(from: '/docs/v1/', to: '/legacy/', type: RedirectConfig::TYPE_PREFIX),
        ];

        $this->assertSame('https://example.com/legacy/a/', $this->match($redirects, '/docs/v1/a/')['location'] ?? null);
        $this->assertSame('https://example.com/manual/v2/', $this->match($redirects, '/docs/v2/')['location'] ?? null);
    }

    public function testExactWinsOverPrefixAndRegex(): void
    {
        $redirects = [
            new RedirectConfig(from: '.*', to: '/regex/', type: RedirectConfig::TYPE_REGEX),
            new RedirectConfig(from: '/docs/', to: '/prefix/', type: RedirectConfig::TYPE_PREFIX),
            new RedirectConfig(from: '/docs/a/', to: '/exact/'),
        ];

        $this->assertSame('https://example.com/exact/', $this->match($redirects, '/docs/a/')['location'] ?? null);
        $this->assertSame('https://example.com/prefix/b/', $this->match($redirects, '/docs/b/')['location'] ?? null);
        $this->assertSame('https://example.com/regex/', $this->match($redirects, '/other/')['location'] ?? null);
    }

    public function testRegexSubstitutesGroups(): void
    {
        $redirects = [new RedirectConfig(from: '^news/(\d+)$', to: '/topics/$1/', type: RedirectConfig::TYPE_REGEX)];

        $this->assertSame(['status' => 301, 'location' => 'https://example.com/topics/123/'], $this->match($redirects, '/news/123'));
        $this->assertNull($this->match($redirects, '/news/abc'));
    }

    /**
     * The matched path has no trailing slash, so adding one is the request
     * itself: /news/123/ would be sent to /news/123/ forever.
     */
    public function testRegexThatOnlyAddsASlashIsSkipped(): void
    {
        $redirects = [new RedirectConfig(from: '^news/(\d+)$', to: '/news/$1/', type: RedirectConfig::TYPE_REGEX)];

        $this->assertNull($this->match($redirects, '/news/123'));
        $this->assertNull($this->match($redirects, '/news/123/'));
    }

    public function testRegexSupportsBracedGroupsAndEncodesThem(): void
    {
        $redirects = [new RedirectConfig(from: '^blog/(\d{4})/(.+)$', to: '/archive/${1}/$2/', type: RedirectConfig::TYPE_REGEX)];

        $this->assertSame(
            'https://example.com/archive/2024/%E4%BC%9A%E7%A4%BE/a/',
            $this->match($redirects, '/blog/2024/%E4%BC%9A%E7%A4%BE/a/')['location'] ?? null
        );
    }

    public function testRegexesApplyInRegistrationOrder(): void
    {
        $redirects = [
            new RedirectConfig(from: '^a/', to: '/first/', type: RedirectConfig::TYPE_REGEX),
            new RedirectConfig(from: '^a/b', to: '/second/', type: RedirectConfig::TYPE_REGEX),
        ];

        $this->assertSame('https://example.com/first/', $this->match($redirects, '/a/b/')['location'] ?? null);
    }

    public function testRegexResultThatIsTheRequestIsSkipped(): void
    {
        $redirects = [
            new RedirectConfig(from: '^(.*)$', to: '/$1/', type: RedirectConfig::TYPE_REGEX),
            new RedirectConfig(from: '^loop$', to: '/fallback/', type: RedirectConfig::TYPE_REGEX),
        ];

        $this->assertSame('https://example.com/fallback/', $this->match($redirects, '/loop/')['location'] ?? null);
        $this->assertNull($this->match([$redirects[0]], '/anything/'));
    }

    public function testRegexCannotBuildAnotherHost(): void
    {
        $redirects = [new RedirectConfig(from: '^go/(.*)$', to: '/$1', type: RedirectConfig::TYPE_REGEX)];

        $location = $this->match($redirects, '/go//evil.example/')['location'] ?? '';

        $this->assertSame('example.com', parse_url($location, PHP_URL_HOST));
    }

    public function testQueryStringIsCarriedOver(): void
    {
        $redirects = [
            new RedirectConfig(from: '/old-page/', to: '/new-page/'),
            new RedirectConfig(from: '/old-dir/', to: '/new-dir/#top', type: RedirectConfig::TYPE_PREFIX),
        ];

        $this->assertSame('https://example.com/new-page/?a=1&b=2', $this->match($redirects, '/old-page/?a=1&b=2')['location'] ?? null);
        $this->assertSame('https://example.com/new-dir/x/?a=1#top', $this->match($redirects, '/old-dir/x/?a=1')['location'] ?? null);
    }

    public function testTargetWithItsOwnQueryStringKeepsIt(): void
    {
        $redirects = [new RedirectConfig(from: '/old-page/', to: '/new-page/?from=old')];

        $this->assertSame('https://example.com/new-page/?from=old', $this->match($redirects, '/old-page/?a=1')['location'] ?? null);
    }

    public function testSubdirectoryInstall(): void
    {
        $home = 'https://example.com/wp/';
        $redirects = [
            new RedirectConfig(from: '/old-page/', to: '/new-page/'),
            new RedirectConfig(from: '/old-dir/', to: '/new-dir/', type: RedirectConfig::TYPE_PREFIX),
        ];

        $this->assertSame('https://example.com/wp/new-page/', $this->match($redirects, '/wp/old-page/', $home)['location'] ?? null);
        $this->assertSame('https://example.com/wp/new-dir/a/', $this->match($redirects, '/wp/old-dir/a/', $home)['location'] ?? null);
    }

    public function testHostsOfAbsoluteTargets(): void
    {
        $redirects = [
            new RedirectConfig(from: '/a/', to: '/b/'),
            new RedirectConfig(from: '/c/', to: 'https://Shop.Example.org/x/'),
            new RedirectConfig(from: '/d/', to: 'https://shop.example.org/y/'),
            new RedirectConfig(from: '/closed/', status: 410),
        ];

        $this->assertSame(['shop.example.org'], Redirects::hosts($redirects));
    }

    public function testAllowedHostsAreAddedDuringTonkatsuRedirectsOnly(): void
    {
        Seo::registerRedirect(new RedirectConfig(from: '/c/', to: 'https://shop.example.org/'));

        $this->assertSame(['example.com'], Redirects::filterAllowedHosts(['example.com']));

        $reflection = new \ReflectionProperty(Redirects::class, 'redirecting');
        $reflection->setAccessible(true);
        $reflection->setValue(null, true);

        $this->assertSame(['example.com', 'shop.example.org'], Redirects::filterAllowedHosts(['example.com']));
        $this->assertSame('not an array', Redirects::filterAllowedHosts('not an array'));
    }

    public function testRegisterHooksBeforeRedirectCanonical(): void
    {
        Redirects::register();

        $this->assertSame(0, has_action('template_redirect', [Redirects::class, 'redirect']));
        $this->assertSame(10, has_action('allowed_redirect_hosts', [Redirects::class, 'filterAllowedHosts']));
    }

    public function testRegisteredPageHiddenByAnExactRedirect(): void
    {
        Seo::registerPage('/contact/', new PageConfig(title: 'Contact'));

        $this->assertTrue(Redirects::hidesRegisteredPage(new RedirectConfig(from: 'contact', to: '/inquiry/')));
        $this->assertFalse(Redirects::hidesRegisteredPage(new RedirectConfig(from: '/other/', to: '/inquiry/')));
        $this->assertFalse(Redirects::hidesRegisteredPage(new RedirectConfig(from: '^contact$', to: '/inquiry/', type: RedirectConfig::TYPE_REGEX)));
    }

    public function testChainedTargets(): void
    {
        $first = new RedirectConfig(from: '/a/', to: '/b/');
        $second = new RedirectConfig(from: '/b/', to: '/c/');
        $intoPrefix = new RedirectConfig(from: '/x/', to: 'https://example.com/old-dir/y/');
        $prefix = new RedirectConfig(from: '/old-dir/', to: '/new-dir/', type: RedirectConfig::TYPE_PREFIX);
        $external = new RedirectConfig(from: '/e/', to: 'https://example.org/a/');
        $redirects = [$first, $second, $intoPrefix, $prefix, $external];

        $this->assertTrue(Redirects::isChained($first, $redirects, self::HOME));
        $this->assertFalse(Redirects::isChained($second, $redirects, self::HOME));
        $this->assertTrue(Redirects::isChained($intoPrefix, $redirects, self::HOME));
        $this->assertFalse(Redirects::isChained($external, $redirects, self::HOME));
    }

    public function testSitePath(): void
    {
        $this->assertSame('a/b', Redirects::sitePath('/a/b/', 'https://example.com/wp/'));
        $this->assertSame('a', Redirects::sitePath('https://EXAMPLE.com/wp/a/', 'https://example.com/wp/'));
        $this->assertNull(Redirects::sitePath('https://example.org/a/', 'https://example.com/'));
    }
}
