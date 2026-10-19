<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use Ufo\McpBundle\Tests\Fixtures\TestKernel;

/**
 * Legacy (session-based) MCP: sessions live in the core cache pool (rpc.cache.pool), per group.
 */
final class SessionTest extends McpKernelTestCase
{
    private const string UNKNOWN_SESSION = '0199e000-0000-7000-8000-000000000000';

    public function testSessionIsStoredInTheCoreCachePool(): void
    {
        $sessionId = $this->initialize('/mcp/internal');

        $pool = self::getContainer()->get('rpc.cache.pool');
        self::assertTrue($pool->hasItem('ufo_mcp_internal_'.$sessionId));
        self::assertFalse($pool->hasItem('ufo_mcp_mcp_'.$sessionId));
    }

    public function testSessionIsBoundToItsGroup(): void
    {
        $sessionId = $this->initialize('/mcp/internal');

        $response = $this->post('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], ['Mcp-Session-Id' => $sessionId]);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testRequestWithoutSessionIsRejected(): void
    {
        $this->initialize();
        $response = $this->post('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);

        self::assertSame(400, $response->getStatusCode());
    }

    public function testUnknownSessionIs404(): void
    {
        $response = $this->post('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], ['Mcp-Session-Id' => self::UNKNOWN_SESSION]);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Session not found or has expired.', $this->json($response)['error']['message']);
    }

    public function testDeleteEndsTheSession(): void
    {
        $sessionId = $this->initialize();

        $response = $this->send(Request::create('http://localhost/mcp', 'DELETE', server: [
            'HTTP_UFO_RPC_TOKEN' => TestKernel::TOKEN, 'HTTP_MCP_SESSION_ID' => $sessionId,
        ]));
        self::assertSame(200, $response->getStatusCode());

        self::assertFalse(self::getContainer()->get('rpc.cache.pool')->hasItem('ufo_mcp_mcp_'.$sessionId));
        $after = $this->post('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], ['Mcp-Session-Id' => $sessionId]);
        self::assertSame(404, $after->getStatusCode());
    }

    public function testSessionSurvivesAKernelRestart(): void
    {
        $sessionId = $this->initialize();
        self::ensureKernelShutdown();

        self::assertArrayHasKey('Catalog.ping', $this->listTools($sessionId));
    }
}
