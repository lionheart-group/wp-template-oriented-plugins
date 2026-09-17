<?php

namespace TofuPlugin\Tests\Unit\Init;

use TofuPlugin\Init\AdminPage;
use TofuPlugin\Tests\Unit\BaseTestCase;

class AdminPageHooksTest extends BaseTestCase
{
    private function capability(): string
    {
        $method = new \ReflectionMethod(AdminPage::class, 'capability');
        $method->setAccessible(true);
        return $method->invoke(null);
    }

    public function testDefaultsToManageOptions(): void
    {
        $this->assertSame('manage_options', $this->capability());
    }

    public function testFilterCanLowerTheRequiredCapability(): void
    {
        add_filter('tofu_admin_page_capability', fn () => 'edit_pages');

        $this->assertSame('edit_pages', $this->capability());
    }

    /**
     * A callback returning something unusable must not leave the records page
     * guarded by an empty or non-string capability, which current_user_can()
     * would treat as a capability nobody has (or, worse, as a user ID).
     *
     * @return array<string, array{mixed}>
     */
    public static function unusableReturnValues(): array
    {
        return [
            'empty string' => [''],
            'null'         => [null],
            'false'        => [false],
            'array'        => [[]],
            'integer'      => [1],
        ];
    }

    /**
     * @dataProvider unusableReturnValues
     */
    public function testUnusableFilterReturnFallsBackToManageOptions(mixed $return): void
    {
        add_filter('tofu_admin_page_capability', fn () => $return);

        $this->assertSame('manage_options', $this->capability());
    }
}
