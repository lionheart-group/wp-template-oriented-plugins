<?php

namespace TobiuoPlugin\Tests\Unit\Structure;

use TobiuoPlugin\Structure\PermalinkConfig;
use TobiuoPlugin\Structure\PostsConfig;
use TobiuoPlugin\Tests\Unit\BaseTestCase;

class PostsConfigTest extends BaseTestCase
{
    public function testDefaults(): void
    {
        $config = new PostsConfig();

        $this->assertNull($config->archive);
        $this->assertNull($config->permalink);
        $this->assertNull($config->permalinkStructure());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validArchives(): array
    {
        return [
            'one segment'  => ['news'],
            'two segments' => ['info/news'],
            'hyphen digit' => ['news-2'],
        ];
    }

    /**
     * @dataProvider validArchives
     */
    public function testValidArchives(string $archive): void
    {
        $this->assertSame($archive, (new PostsConfig(archive: $archive))->archive);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidArchives(): array
    {
        return [
            'empty'          => [''],
            'leading slash'  => ['/news'],
            'trailing slash' => ['news/'],
            'double slash'   => ['info//news'],
            'uppercase'      => ['News'],
            'underscore'     => ['news_list'],
            'multibyte'      => ['お知らせ'],
            'tag'            => ['%year%'],
            'markup'         => ['<b>'],
        ];
    }

    /**
     * @dataProvider invalidArchives
     */
    public function testInvalidArchives(string $archive): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("PostsConfig: archive '" . esc_html($archive) . "'");

        new PostsConfig(archive: $archive);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function supportedStructures(): array
    {
        return [
            'postname'  => ['/%postname%/'],
            'post id'   => ['/%post_id%'],
            'dates'     => ['/%year%/%monthnum%/%day%/%hour%%minute%%second%/%postname%/'],
            'author'    => ['/%author%/%post_id%/'],
            'category'  => ['/%category%/%postname%/'],
        ];
    }

    /**
     * @dataProvider supportedStructures
     */
    public function testSupportedStructures(string $structure): void
    {
        $this->assertSame($structure, (new PostsConfig(permalink: new PermalinkConfig(structure: $structure)))->permalinkStructure());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unsupportedStructures(): array
    {
        return [
            'post_tag'        => ['/%post_tag%/%postname%/', "contains '%post_tag%'."],
            'custom'          => ['/%area%/%postname%/', "contains '%area%'."],
            'two unsupported' => ['/%area%/%category%/%genre%/%postname%/', "contains '%area%&#039;, &#039;%genre%'."],
        ];
    }

    /**
     * @dataProvider unsupportedStructures
     */
    public function testUnsupportedTagsAreRejected(string $structure, string $tags): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($tags);

        new PostsConfig(permalink: new PermalinkConfig(structure: $structure));
    }

    public function testDateArchiveIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PostsConfig: dateArchive must be false');

        new PostsConfig(permalink: new PermalinkConfig(structure: '/%postname%/', dateArchive: true));
    }

    public function testAuthorArchiveIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PostsConfig: authorArchive must be false');

        new PostsConfig(permalink: new PermalinkConfig(structure: '/%postname%/', authorArchive: true));
    }

    public function testDateFrontIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dateFront must be null');

        new PostsConfig(permalink: new PermalinkConfig(structure: '/%post_id%/', dateFront: ''));
    }

    public function testPermalinkStructureIsBelowTheArchive(): void
    {
        $permalink = new PermalinkConfig(structure: '/%postname%/');

        $this->assertSame('/news/%postname%/', (new PostsConfig(archive: 'news', permalink: $permalink))->permalinkStructure());
        $this->assertSame('/info/news/%postname%/', (new PostsConfig(archive: 'info/news', permalink: $permalink))->permalinkStructure());
        $this->assertSame('/%postname%/', (new PostsConfig(permalink: $permalink))->permalinkStructure());
        $this->assertNull((new PostsConfig(archive: 'news'))->permalinkStructure());
    }

    public function testFromArray(): void
    {
        $config = PostsConfig::fromArray(['archive' => 'news', 'permalink' => ['structure' => '/%postname%/']]);

        $this->assertSame('news', $config->archive);
        $this->assertSame('/news/%postname%/', $config->permalinkStructure());
    }

    public function testFromArrayAcceptsObjectsAndNull(): void
    {
        $permalink = new PermalinkConfig(structure: '/%post_id%/');

        $this->assertSame($permalink, PostsConfig::fromArray(['permalink' => $permalink])->permalink);
        $this->assertNull(PostsConfig::fromArray(['archive' => null, 'permalink' => null])->archive);
        $this->assertNull(PostsConfig::fromArray([])->permalink);
    }

    public function testFromArrayRejectsUnknownKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PostsConfig &#039;posts.php&#039;: unknown key(s) "has_archive"');

        PostsConfig::fromArray(['has_archive' => 'news'], 'posts.php');
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function wrongTypes(): array
    {
        return [
            'archive true'     => [['archive' => true], '"archive" must be a string or null, got bool'],
            'archive array'    => [['archive' => ['news']], '"archive" must be a string or null, got array'],
            'permalink string' => [['permalink' => '/%postname%/'], '"permalink" must be a PermalinkConfig, an array or null, got string'],
        ];
    }

    /**
     * @dataProvider wrongTypes
     * @param array<string, mixed> $values
     */
    public function testFromArrayRejectsWrongTypes(array $values, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        PostsConfig::fromArray($values);
    }

    public function testFromArrayPermalinkErrorsNameTheLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PermalinkConfig &#039;posts.php&#039;: unknown key(s) "date_archive"');

        PostsConfig::fromArray(['permalink' => ['structure' => '/%postname%/', 'date_archive' => true]], 'posts.php');
    }
}
