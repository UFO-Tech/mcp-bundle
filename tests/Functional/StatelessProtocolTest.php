<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Functional;

/**
 * MCP 2026-07-28: no handshake and no session; every request carries its protocol version in params._meta
 * and the method (and tool name) in the Mcp-Method / Mcp-Name headers.
 */
final class StatelessProtocolTest extends McpKernelTestCase
{
    private function modern(string $method, array $params = [], array $headers = [], string $path = '/mcp'): array
    {
        $params['_meta'] = [
            'io.modelcontextprotocol/protocolVersion' => self::MODERN_VERSION,
            'io.modelcontextprotocol/clientInfo' => ['name' => 'phpunit', 'version' => '1'],
            'io.modelcontextprotocol/clientCapabilities' => new \stdClass(),
        ];
        $response = $this->post($path, ['jsonrpc' => '2.0', 'id' => 5, 'method' => $method, 'params' => $params], [
            'MCP-Protocol-Version' => self::MODERN_VERSION, 'Mcp-Method' => $method,
        ] + $headers);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertFalse($response->headers->has('Mcp-Session-Id'));

        return $this->json($response)['result'];
    }

    public function testToolsListWithoutHandshake(): void
    {
        $names = array_column($this->modern('tools/list', headers: ['Accept-Language' => 'uk'])['tools'], 'title', 'name');

        self::assertSame('Пінг', $names['Catalog.ping']);
        self::assertArrayNotHasKey('Internal.secret', $names);
    }

    public function testToolsCallWithoutHandshake(): void
    {
        $result = $this->modern('tools/call', ['name' => 'Catalog.ping', 'arguments' => ['who' => 'm']], ['Mcp-Name' => 'Catalog.ping']);

        self::assertFalse($result['isError']);
        self::assertSame(['result' => 'pong m'], $result['structuredContent']);
        self::assertSame(['name' => 'io.test/app', 'version' => 'v1', 'title' => 'Test App'], $result['_meta']['io.modelcontextprotocol/serverInfo']);
    }

    public function testGroupAndVersionOfTheRoute(): void
    {
        $result = $this->modern('tools/call', ['name' => 'Internal.secret', 'arguments' => new \stdClass()], ['Mcp-Name' => 'Internal.secret'], '/mcp/internal/v1');

        self::assertSame(['result' => 'secret'], $result['structuredContent']);
        self::assertSame('io.test/internal', $result['_meta']['io.modelcontextprotocol/serverInfo']['name']);
    }

    public function testMissingMethodHeaderIsRejected(): void
    {
        $response = $this->post('/mcp', [
            'jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/list',
            'params' => ['_meta' => ['io.modelcontextprotocol/protocolVersion' => self::MODERN_VERSION]],
        ], ['MCP-Protocol-Version' => self::MODERN_VERSION]);

        self::assertSame(400, $response->getStatusCode());
    }
}
