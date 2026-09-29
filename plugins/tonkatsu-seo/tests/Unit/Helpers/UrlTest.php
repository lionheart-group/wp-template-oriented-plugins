<?php

namespace TonkatsuPlugin\Tests\Unit\Helpers;

use TonkatsuPlugin\Helpers\Url;
use TonkatsuPlugin\Tests\Unit\BaseTestCase;

class UrlTest extends BaseTestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function urls(): array
    {
        return [
            'https'              => ['https://example.com/a/', true],
            'http with query'    => ['http://example.com/?a=1&b=2', true],
            'japanese path'      => ['https://example.com/会社概要/', true],
            'root relative'      => ['/wp-content/ogp.png', true],
            'protocol relative'  => ['//example.com/ogp.png', false],
            'relative'           => ['ogp.png', false],
            'javascript'         => ['javascript:alert(1)', false],
            'ftp'                => ['ftp://example.com/', false],
            'whitespace'         => ['https://example.com/a b', false],
            'empty'              => ['', false],
        ];
    }

    /**
     * @dataProvider urls
     */
    public function testIsValid(string $url, bool $expected): void
    {
        $this->assertSame($expected, Url::isValid($url));
    }

    public function testAbsoluteUsesTheOriginOfTheHomeUrl(): void
    {
        $this->assertSame(
            'https://example.com:8443/ogp.png',
            Url::absolute('/ogp.png', 'https://example.com:8443/wp/')
        );
    }

    public function testAbsoluteLeavesAbsoluteUrlsAlone(): void
    {
        $this->assertSame('https://cdn.example.net/a.png', Url::absolute('https://cdn.example.net/a.png', 'https://example.com/'));
    }
}
