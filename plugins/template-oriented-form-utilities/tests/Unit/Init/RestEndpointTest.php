<?php

namespace TofuPlugin\Tests\Unit\Init;

use TofuPlugin\Consts;
use TofuPlugin\Tests\Unit\BaseTestCase;

class RestEndpointTest extends BaseTestCase
{
    public function testRestNamespaceConstantIsDefined(): void
    {
        $this->assertSame('tofu/v1', Consts::REST_NAMESPACE);
    }

    public function testRestNonceActionFormatConstantIsDefined(): void
    {
        $this->assertStringContainsString('%s', Consts::REST_NONCE_ACTION_FORMAT);
    }

    public function testRestNonceActionFormatProducesExpectedString(): void
    {
        $key = 'contact';
        $action = sprintf(Consts::REST_NONCE_ACTION_FORMAT, $key, 'input');
        $this->assertSame('_tofu_contact_input_rest_nonce', $action);
    }

    public function testRestNamespaceContainsVersion(): void
    {
        $this->assertStringContainsString('v1', Consts::REST_NAMESPACE);
    }

    public function testRestEndpointClassExists(): void
    {
        $this->assertTrue(class_exists(\TofuPlugin\Init\RestEndpoint::class));
    }

    public function testRestEndpointHasInitMethod(): void
    {
        $this->assertTrue(method_exists(\TofuPlugin\Init\RestEndpoint::class, 'init'));
    }

    public function testRestEndpointHasRegisterRoutesMethod(): void
    {
        $this->assertTrue(method_exists(\TofuPlugin\Init\RestEndpoint::class, 'registerRoutes'));
    }

    public function testRestEndpointHasHandleNonceMethod(): void
    {
        $this->assertTrue(method_exists(\TofuPlugin\Init\RestEndpoint::class, 'handleNonce'));
    }

    public function testRestEndpointHasHandleInputMethod(): void
    {
        $this->assertTrue(method_exists(\TofuPlugin\Init\RestEndpoint::class, 'handleInput'));
    }

    public function testRestEndpointHasHandleConfirmMethod(): void
    {
        $this->assertTrue(method_exists(\TofuPlugin\Init\RestEndpoint::class, 'handleConfirm'));
    }
}
