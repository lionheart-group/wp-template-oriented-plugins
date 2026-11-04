<?php

namespace TonkatsuPlugin\Tests\Unit\Structure;

use TonkatsuPlugin\Structure\OrganizationConfig;
use TonkatsuPlugin\Tests\Unit\BaseTestCase;

class OrganizationConfigTest extends BaseTestCase
{
    public function testAcceptsValidValues(): void
    {
        $config = new OrganizationConfig(
            name: '株式会社サンプル',
            url: 'https://example.com/',
            logo: '/logo.png',
            sameAs: ['https://x.com/example', 'https://ja.wikipedia.org/wiki/サンプル'],
        );

        $this->assertCount(2, $config->sameAs);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OrganizationConfig(name: ' ');
    }

    public function testRejectsAnInvalidUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OrganizationConfig(name: 'Example', url: 'example.com');
    }

    public function testRejectsAnInvalidLogo(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OrganizationConfig(name: 'Example', logo: 'javascript:alert(1)');
    }

    /**
     * @return array<string, array{array<mixed>}>
     */
    public static function invalidSameAs(): array
    {
        return [
            'not a url'  => [['x.com/example']],
            'not string' => [[123]],
            'mixed'      => [['https://x.com/example', null]],
        ];
    }

    /**
     * @dataProvider invalidSameAs
     * @param array<mixed> $sameAs
     */
    public function testRejectsInvalidSameAs(array $sameAs): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OrganizationConfig(name: 'Example', sameAs: $sameAs);
    }

    public function testAcceptsTheLocalDetails(): void
    {
        $config = new OrganizationConfig(
            name: '株式会社サンプル',
            type: 'Corporation',
            alternateName: 'Sample Co., Ltd.',
            address: ['postalCode' => '460-0003', 'addressCountry' => 'JP', 'streetAddress' => '錦3丁目1-1'],
            telephone: '+81-52-000-0000',
            faxNumber: '+81-52-000-0001',
            foundingDate: '1983-04',
        );

        $this->assertSame('Corporation', $config->type);
        $this->assertSame('460-0003', $config->address['postalCode']);
    }

    public function testDefaultsToOrganization(): void
    {
        $this->assertSame('Organization', (new OrganizationConfig(name: 'Example'))->type);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidTypes(): array
    {
        return [
            'empty'      => [''],
            'url'        => ['https://schema.org/Corporation'],
            'with space' => ['Local Business'],
        ];
    }

    /**
     * @dataProvider invalidTypes
     */
    public function testRejectsAnInvalidType(string $type): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OrganizationConfig(name: 'Example', type: $type);
    }

    /**
     * @return array<string, array{array<mixed>}>
     */
    public static function invalidAddresses(): array
    {
        return [
            'unknown key'  => [['zip' => '460-0003']],
            'list'         => [['460-0003']],
            'empty value'  => [['postalCode' => ' ']],
            'not a string' => [['postalCode' => 4600003]],
        ];
    }

    /**
     * @dataProvider invalidAddresses
     * @param array<mixed> $address
     */
    public function testRejectsAnInvalidAddress(array $address): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OrganizationConfig(name: 'Example', address: $address);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function foundingDates(): array
    {
        return [
            'year'        => ['1983', true],
            'month'       => ['1983-04', true],
            'day'         => ['1983-04-01', true],
            'japanese'    => ['1983年', false],
            'bad month'   => ['1983-13', false],
            'no padding'  => ['1983-4-1', false],
            'with time'   => ['1983-04-01T00:00', false],
        ];
    }

    /**
     * @dataProvider foundingDates
     */
    public function testFoundingDateFormat(string $date, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(\InvalidArgumentException::class);
        }

        $this->assertSame($date, (new OrganizationConfig(name: 'Example', foundingDate: $date))->foundingDate);
    }
}
