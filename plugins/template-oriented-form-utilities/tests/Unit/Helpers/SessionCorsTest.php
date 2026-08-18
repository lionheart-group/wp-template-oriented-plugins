<?php

namespace TofuPlugin\Tests\Unit\Helpers;

use TofuPlugin\Helpers\Session;
use TofuPlugin\Tests\Unit\BaseTestCase;

class SessionCorsTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset CORS mode between tests via reflection
        $ref = new \ReflectionProperty(Session::class, 'corsMode');
        $ref->setAccessible(true);
        $ref->setValue(null, false);
    }

    public function testCorsDisabledByDefault(): void
    {
        $ref = new \ReflectionProperty(Session::class, 'corsMode');
        $ref->setAccessible(true);
        $this->assertFalse($ref->getValue());
    }

    public function testEnableCorsSetsFlag(): void
    {
        Session::enableCors();

        $ref = new \ReflectionProperty(Session::class, 'corsMode');
        $ref->setAccessible(true);
        $this->assertTrue($ref->getValue());
    }

    public function testEnableCorsIsIdempotent(): void
    {
        Session::enableCors();
        Session::enableCors();

        $ref = new \ReflectionProperty(Session::class, 'corsMode');
        $ref->setAccessible(true);
        $this->assertTrue($ref->getValue());
    }
}
