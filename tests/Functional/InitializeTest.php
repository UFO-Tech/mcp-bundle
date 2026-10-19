<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Functional;

use Ufo\McpBundle\Tests\Fixtures\TestKernel;

/**
 * The server identity in "initialize": mcp_server_info, per-group overrides, the API version of the route.
 */
final class InitializeTest extends McpKernelTestCase
{
    private function initializeResult(string $path): array
    {
        $response = $this->post($path, [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
            'params' => ['protocolVersion' => self::LEGACY_VERSION, 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'phpunit', 'version' => '1']],
        ]);
        self::assertSame(200, $response->getStatusCode());

        return $this->json($response)['result'];
    }

    public function testBundleWideServerInfo(): void
    {
        $result = $this->initializeResult('/mcp');

        self::assertSame(self::LEGACY_VERSION, $result['protocolVersion']);
        self::assertSame(['name' => 'io.test/app', 'version' => 'v1', 'title' => 'Test App'], $result['serverInfo']);
        self::assertArrayHasKey('tools', $result['capabilities']);
        self::assertArrayNotHasKey('instructions', $result);
    }

    public function testGroupOverridesAndInheritsTheRest(): void
    {
        $result = $this->initializeResult('/mcp/internal');

        self::assertSame('io.test/internal', $result['serverInfo']['name']);
        self::assertSame('Test App', $result['serverInfo']['title'], 'inherited from the bundle-wide mcp_server_info');
        self::assertSame('Internal tools.', $result['instructions']);
    }

    public function testVersionIsTheRouteSegment(): void
    {
        self::assertSame('v2', $this->initializeResult('/mcp/v2')['serverInfo']['version']);
        self::assertSame('v1', $this->initializeResult('/mcp/v1')['serverInfo']['version']);
    }

    public function testDefaultNameWithoutServerInfo(): void
    {
        TestKernel::$mcpConfig = ['instructions' => 'Use the catalog.'];

        $result = $this->initializeResult('/mcp');

        self::assertSame(['name' => 'ufo-rpc', 'version' => 'v1'], $result['serverInfo']);
        self::assertSame('Use the catalog.', $result['instructions']);
    }
}
