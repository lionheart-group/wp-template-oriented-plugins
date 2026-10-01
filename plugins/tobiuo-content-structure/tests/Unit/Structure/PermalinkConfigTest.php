<?php

namespace TobiuoPlugin\Tests\Unit\Structure;

use TobiuoPlugin\Structure\PermalinkConfig;
use TobiuoPlugin\Tests\Unit\BaseTestCase;

class PermalinkConfigTest extends BaseTestCase
{
    public function testDefaults(): void
    {
        $config = new PermalinkConfig(structure: '/%postname%/');

        $this->assertSame('/%postname%/', $config->structure);
        $this->assertFalse($config->dateArchive);
        $this->assertFalse($config->authorArchive);
        $this->assertNull($config->dateFront);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validStructures(): array
    {
        return [
            'postname'                 => ['/%postname%/'],
            'post id'                  => ['/%post_id%/'],
            'no trailing slash'        => ['/%postname%'],
            'taxonomy'                 => ['/%case_category%/%postname%/'],
            'two taxonomies'           => ['/%area%/%case_category%/%postname%/'],
            'dates'                    => ['/%year%/%monthnum%/%day%/%hour%%minute%%second%/%postname%/'],
            'author'                   => ['/%author%/%post_id%/'],
            'literal text'             => ['/news-%post_id%.html'],
            'hyphenated taxonomy'      => ['/%case-type%/%postname%/'],
            'postname and post id'     => ['/%post_id%-%postname%/'],
            '32-character taxonomy'    => ['/%' . str_repeat('t', 32) . '%/%postname%/'],
        ];
    }

    /**
     * @dataProvider validStructures
     */
    public function testValidStructures(string $structure): void
    {
        $this->assertSame($structure, (new PermalinkConfig(structure: $structure))->structure);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidStructures(): array
    {
        return [
            'no leading slash'      => ['%postname%/', "must start with '/'"],
            'empty'                 => ['', "must start with '/'"],
            'no post tag'           => ['/%case_category%/', 'must contain %postname% or %post_id%'],
            'only slash'            => ['/', 'must contain %postname% or %post_id%'],
            'double slash'          => ['//%postname%/', "'//'"],
            'query string'          => ['/%postname%/?a=1', "'?'"],
            'fragment'              => ['/%postname%/#top', "'#'"],
            'whitespace'            => ['/%postname% /', 'whitespace'],
            'uppercase tag'         => ['/%Category%/%postname%/', "'%Category%'"],
            'uppercase postname'    => ['/%POSTNAME%/', "'%POSTNAME%'"],
            'empty tag'             => ['/%%/%postname%/', "'%%'"],
            'stray percent'         => ['/100%/%postname%/', "'%' that is not part of a tag"],
            'percent across slash'  => ['/%a/b%/%postname%/', "'%' that is not part of a tag"],
            'duplicate postname'    => ['/%postname%/%postname%/', "'%postname%' more than once"],
            'duplicate taxonomy'    => ['/%area%/%area%/%post_id%/', "'%area%' more than once"],
            '33-character taxonomy' => ['/%' . str_repeat('t', 33) . '%/%postname%/', 'neither one of'],
        ];
    }

    /**
     * @dataProvider invalidStructures
     */
    public function testInvalidStructures(string $structure, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new PermalinkConfig(structure: $structure);
    }

    public function testMessagesAreEscaped(): void
    {
        try {
            new PermalinkConfig(structure: '<b>');
            $this->fail('Expected an exception.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('&lt;b&gt;', $e->getMessage());
            $this->assertStringNotContainsString('<b>', $e->getMessage());
        }
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function validDateFronts(): array
    {
        return [
            'auto'     => [null],
            'none'     => [''],
            'date'     => ['/date'],
            'nested'   => ['/archive/date'],
        ];
    }

    /**
     * @dataProvider validDateFronts
     */
    public function testValidDateFronts(?string $dateFront): void
    {
        $this->assertSame($dateFront, (new PermalinkConfig(structure: '/%post_id%/', dateFront: $dateFront))->dateFront);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDateFronts(): array
    {
        return [
            'no leading slash' => ['date'],
            'trailing slash'   => ['/date/'],
            'only slash'       => ['/'],
            'double slash'     => ['/a//b'],
            'tag'              => ['/%year%'],
        ];
    }

    /**
     * @dataProvider invalidDateFronts
     */
    public function testInvalidDateFronts(string $dateFront): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dateFront');

        new PermalinkConfig(structure: '/%post_id%/', dateFront: $dateFront);
    }

    public function testTaxonomyTagsAreTheNonCoreTagsInOrder(): void
    {
        $config = new PermalinkConfig(structure: '/%year%/%area%/%case_category%/%author%/%postname%/');

        $this->assertSame(['area', 'case_category'], $config->taxonomyTags());
        $this->assertSame([], (new PermalinkConfig(structure: '/%year%/%postname%/'))->taxonomyTags());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function contextTags(): array
    {
        return [
            'postname only' => ['/%postname%/', false],
            'post id only'  => ['/%post_id%/', false],
            'both'          => ['/%post_id%-%postname%/', false],
            'taxonomy'      => ['/%case_category%/%postname%/', true],
            'year'          => ['/%year%/%postname%/', true],
            'second'        => ['/%second%-%post_id%/', true],
            'author'        => ['/%author%/%postname%/', true],
        ];
    }

    /**
     * @dataProvider contextTags
     */
    public function testHasContextTags(string $structure, bool $expected): void
    {
        $this->assertSame($expected, (new PermalinkConfig(structure: $structure))->hasContextTags());
    }

    public function testTrailingSlashFollowsTheStructure(): void
    {
        $this->assertTrue((new PermalinkConfig(structure: '/%postname%/'))->hasTrailingSlash());
        $this->assertFalse((new PermalinkConfig(structure: '/%postname%'))->hasTrailingSlash());
        $this->assertFalse((new PermalinkConfig(structure: '/%post_id%.html'))->hasTrailingSlash());
    }

    public function testFromArray(): void
    {
        $config = PermalinkConfig::fromArray([
            'structure'     => '/%year%/%post_id%/',
            'dateArchive'   => true,
            'authorArchive' => true,
            'dateFront'     => '',
        ]);

        $this->assertSame('/%year%/%post_id%/', $config->structure);
        $this->assertTrue($config->dateArchive);
        $this->assertTrue($config->authorArchive);
        $this->assertSame('', $config->dateFront);
    }

    public function testFromArrayAcceptsNullDateFront(): void
    {
        $this->assertNull(PermalinkConfig::fromArray(['structure' => '/%postname%/', 'dateFront' => null])->dateFront);
    }

    public function testFromArrayRejectsUnknownKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PermalinkConfig &#039;case&#039;: unknown key(s) "date_archive"');

        PermalinkConfig::fromArray(['structure' => '/%postname%/', 'date_archive' => true], 'case');
    }

    public function testFromArrayRequiresAStructure(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"structure" is required');

        PermalinkConfig::fromArray(['dateArchive' => true]);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function wrongTypes(): array
    {
        return [
            'structure int'     => [['structure' => 1], '"structure" must be a string, got int'],
            'structure null'    => [['structure' => null], '"structure" must be a string, got null'],
            'dateArchive "yes"' => [['structure' => '/%postname%/', 'dateArchive' => 'yes'], '"dateArchive" must be a bool, got string'],
            'authorArchive 1'   => [['structure' => '/%postname%/', 'authorArchive' => 1], '"authorArchive" must be a bool, got int'],
            'dateFront false'   => [['structure' => '/%postname%/', 'dateFront' => false], '"dateFront" must be a string or null, got bool'],
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

        PermalinkConfig::fromArray($values);
    }
}
