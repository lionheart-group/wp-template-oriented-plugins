<?php

namespace TonkatsuPlugin\Tests\Unit\Structure;

use TonkatsuPlugin\Structure\RedirectConfig;
use TonkatsuPlugin\Tests\Unit\BaseTestCase;

class RedirectConfigTest extends BaseTestCase
{
    public function testDefaultsAreAnExact301(): void
    {
        $config = new RedirectConfig(from: '/old-page/', to: '/new-page/');

        $this->assertSame(301, $config->status);
        $this->assertSame(RedirectConfig::TYPE_EXACT, $config->type);
        $this->assertSame('old-page', $config->path);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function sources(): array
    {
        return [
            'no slashes'      => ['old-page', 'old-page'],
            'full url'        => ['https://example.com/old-page/?a=1', 'old-page'],
            'percent-encoded' => ['/%E6%97%A7%E3%83%9A%E3%83%BC%E3%82%B8/', '旧ページ'],
            'front page'      => ['/', ''],
        ];
    }

    /**
     * @dataProvider sources
     */
    public function testExactSourcesAreNormalized(string $from, string $expected): void
    {
        $this->assertSame($expected, (new RedirectConfig(from: $from, to: 'https://example.org/'))->path);
    }

    public function testRegexSourceIsKeptAsWritten(): void
    {
        $config = new RedirectConfig(from: '^news/(\d+)$', to: '/news/$1/', type: RedirectConfig::TYPE_REGEX);

        $this->assertSame('^news/(\d+)$', $config->path);
        $this->assertSame('~^news/(\d+)$~u', $config->pattern());
    }

    public function testTildeInARegexIsEscaped(): void
    {
        $config = new RedirectConfig(from: '^~user/(.+)$', to: '/members/$1/', type: RedirectConfig::TYPE_REGEX);

        $this->assertSame('~^\~user/(.+)$~u', $config->pattern());
        $this->assertSame(1, preg_match($config->pattern(), '~user/a'));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function statuses(): array
    {
        return ['301' => [301], '302' => [302], '307' => [307], '308' => [308]];
    }

    /**
     * @dataProvider statuses
     */
    public function testRedirectStatusesAreAccepted(int $status): void
    {
        $this->assertSame($status, (new RedirectConfig(from: '/a/', to: '/b/', status: $status))->status);
    }

    public function testOtherStatusesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('status 303');

        new RedirectConfig(from: '/a/', to: '/b/', status: 303);
    }

    public function testGoneNeedsNoTarget(): void
    {
        $config = new RedirectConfig(from: '/closed/', status: 410);

        $this->assertNull($config->to);
    }

    public function testGoneRejectsATarget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('410');

        new RedirectConfig(from: '/closed/', to: '/', status: 410);
    }

    public function testRedirectNeedsATarget(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RedirectConfig(from: '/a/');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidTargets(): array
    {
        return [
            'relative'          => ['new-page/'],
            'protocol relative' => ['//example.org/'],
            'javascript'        => ['javascript:alert(1)'],
            'empty'             => [''],
        ];
    }

    /**
     * @dataProvider invalidTargets
     */
    public function testInvalidTargetsAreRejected(string $to): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RedirectConfig(from: '/a/', to: $to);
    }

    public function testUnknownTypeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown type');

        new RedirectConfig(from: '/a/', to: '/b/', type: 'wildcard');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function loops(): array
    {
        return [
            'exact, same path'          => ['/a/', '/a/', RedirectConfig::TYPE_EXACT],
            'exact, other spelling'     => ['a', '/a/?ref=x', RedirectConfig::TYPE_EXACT],
            'exact, front page'         => ['/', '/', RedirectConfig::TYPE_EXACT],
            'prefix, same path'         => ['/a/', '/a/', RedirectConfig::TYPE_PREFIX],
            'prefix, below the source'  => ['/a/', '/a/b/', RedirectConfig::TYPE_PREFIX],
        ];
    }

    /**
     * @dataProvider loops
     */
    public function testSelfRedirectsAreRejected(string $from, string $to, string $type): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('back to itself');

        new RedirectConfig(from: $from, to: $to, type: $type);
    }

    public function testPrefixMayMoveToASibling(): void
    {
        $config = new RedirectConfig(from: '/a/', to: '/ab/', type: RedirectConfig::TYPE_PREFIX);

        $this->assertSame('a', $config->path);
    }

    public function testPrefixCannotStartAtTheFrontPage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('front page');

        new RedirectConfig(from: '/', to: 'https://example.org/', type: RedirectConfig::TYPE_PREFIX);
    }

    public function testEmptyRegexIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RedirectConfig(from: '', to: '/b/', type: RedirectConfig::TYPE_REGEX);
    }

    public function testInvalidRegexNamesTheProblem(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/invalid regex \(.*missing closing parenthesis/');

        new RedirectConfig(from: '^news/(\d+$', to: '/news/$1/', type: RedirectConfig::TYPE_REGEX);
    }

    public function testFromArrayMapsEveryKey(): void
    {
        $config = RedirectConfig::fromArray([
            'from'   => '/campaign/',
            'to'     => 'https://example.org/',
            'status' => 302,
            'type'   => 'prefix',
        ]);

        $this->assertSame('campaign', $config->path);
        $this->assertSame('https://example.org/', $config->to);
        $this->assertSame(302, $config->status);
        $this->assertSame(RedirectConfig::TYPE_PREFIX, $config->type);
    }

    public function testFromArrayRejectsUnknownKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(esc_html("RedirectConfig 'redirect #2'") . ': unknown key(s) "code"');

        RedirectConfig::fromArray(['from' => '/a/', 'to' => '/b/', 'code' => 302], 'redirect #2');
    }

    public function testFromArrayRejectsAStringStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"status" must be an int, got string');

        RedirectConfig::fromArray(['from' => '/a/', 'to' => '/b/', 'status' => '302']);
    }

    public function testFromArrayRejectsANonStringSource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"from" must be a string');

        RedirectConfig::fromArray(['from' => null, 'to' => '/b/']);
    }

    public function testFromArrayRequiresASource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"from" is required');

        RedirectConfig::fromArray(['to' => '/b/']);
    }

    public function testErrorMessagesAreEscaped(): void
    {
        try {
            new RedirectConfig(from: '/<b>/', to: 'javascript:alert(1)');
            $this->fail('Expected an exception.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringNotContainsString('<b>', $e->getMessage());
        }
    }
}
