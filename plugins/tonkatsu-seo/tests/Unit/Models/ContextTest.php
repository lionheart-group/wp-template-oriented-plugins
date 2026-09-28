<?php

namespace ToroPlugin\Tests\Unit\Models;

use ToroPlugin\Models\Context;
use ToroPlugin\Tests\Unit\BaseTestCase;

class ContextTest extends BaseTestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function relativePaths(): array
    {
        return [
            'root install'         => ['https://example.com/company/', 'https://example.com/', 'company'],
            'home itself'          => ['https://example.com/', 'https://example.com/', ''],
            'subdirectory install' => ['https://example.com/wp/company/', 'https://example.com/wp/', 'company'],
            'subdirectory home'    => ['https://example.com/wp/', 'https://example.com/wp/', ''],
            'request uri'          => ['/wp/news/page/2/?x=1', 'https://example.com/wp/', 'news/page/2'],
            'prefix is not a dir'  => ['https://example.com/wpx/', 'https://example.com/wp/', 'wpx'],
        ];
    }

    /**
     * @dataProvider relativePaths
     */
    public function testRelativePath(string $url, string $home, string $expected): void
    {
        $this->assertSame($expected, Context::relativePath($url, $home));
    }

    public function testRejectsAnUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Context(type: 'archive');
    }

    public function testStaticFrontPageIsSingularButThePostsPageIsNot(): void
    {
        $post = $this->makePost();

        $this->assertTrue((new Context(type: Context::TYPE_FRONT, object: $post))->isSingular());
        $this->assertFalse((new Context(type: Context::TYPE_FRONT))->isSingular());
        $this->assertFalse((new Context(type: Context::TYPE_HOME, object: $post))->isSingular());
        $this->assertTrue((new Context(type: Context::TYPE_SINGULAR, object: $post))->isSingular());
    }

    public function testPostIsOnlyReturnedForPosts(): void
    {
        $post = $this->makePost();

        $this->assertSame($post, (new Context(type: Context::TYPE_SINGULAR, object: $post))->post());
        $this->assertNull((new Context(type: Context::TYPE_TAXONOMY, object: new \stdClass()))->post());
    }
}
