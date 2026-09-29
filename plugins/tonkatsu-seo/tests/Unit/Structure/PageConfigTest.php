<?php

namespace ToroPlugin\Tests\Unit\Structure;

use ToroPlugin\Structure\PageConfig;
use ToroPlugin\Tests\Unit\BaseTestCase;

class PageConfigTest extends BaseTestCase
{
    public function testConstructorRejectsAnInvalidCanonical(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PageConfig(canonical: 'example.com/about/');
    }

    public function testConstructorRejectsAnInvalidOgImage(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PageConfig(ogImage: 'ogp.png');
    }

    public function testEmptyStringsAreAcceptedAsPlaceholders(): void
    {
        $config = new PageConfig(title: '', ogImage: '', canonical: '');

        $this->assertSame('', $config->title);
    }

    public function testFromArrayMapsEveryKey(): void
    {
        $config = PageConfig::fromArray([
            'title'       => '会社概要',
            'description' => 'About us.',
            'ogImage'     => 'https://example.com/ogp.png',
            'noindex'     => true,
            'nofollow'    => true,
            'canonical'   => '/company/',
        ]);

        $this->assertSame('会社概要', $config->title);
        $this->assertSame('About us.', $config->description);
        $this->assertSame('https://example.com/ogp.png', $config->ogImage);
        $this->assertTrue($config->noindex);
        $this->assertTrue($config->nofollow);
        $this->assertSame('/company/', $config->canonical);
    }

    public function testFromArrayOfNothingIsTheDefaults(): void
    {
        $config = PageConfig::fromArray([]);

        $this->assertNull($config->title);
        $this->assertFalse($config->noindex);
    }

    /**
     * A typo must fail loudly: silently ignoring `no_index` would leave the
     * page indexable with nobody noticing.
     */
    public function testFromArrayRejectsUnknownKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"no_index"');

        PageConfig::fromArray(['title' => 'Thanks', 'no_index' => true], 'thanks');
    }

    public function testFromArrayNamesTheLabelInErrors(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(esc_html("PageConfig 'contact/thanks'"));

        PageConfig::fromArray(['og_image' => 'x'], 'contact/thanks');
    }

    public function testFromArrayRejectsAList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PageConfig::fromArray(['Title only']);
    }

    /**
     * Without an explicit check, PHP's coercion would turn 'yes' into true
     * and 1 into '1' when spreading into the constructor.
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function wronglyTypedValues(): array
    {
        return [
            'string noindex' => [['noindex' => 'yes']],
            'int nofollow'   => [['nofollow' => 1]],
            'int title'      => [['title' => 1]],
            'array desc'     => [['description' => ['a']]],
        ];
    }

    /**
     * @dataProvider wronglyTypedValues
     * @param array<string, mixed> $values
     */
    public function testFromArrayRejectsWrongTypes(array $values): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PageConfig::fromArray($values);
    }

    /**
     * Messages reach WordPress's error page as HTML, so values are escaped.
     */
    public function testValuesInErrorMessagesAreEscaped(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('&lt;script&gt;');

        new PageConfig(ogImage: '<script>');
    }
}
