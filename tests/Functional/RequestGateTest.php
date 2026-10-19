<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Ufo\McpBundle\Availability\McpAvailabilityCheckerInterface;
use Ufo\McpBundle\Tests\Fixtures\Services\InternalOffAvailabilityChecker;
use Ufo\McpBundle\Tests\Fixtures\TestKernel;

/**
 * McpRequestListener: availability (404), token (401/403) and the CORS preflight, before any action runs.
 */
final class RequestGateTest extends McpKernelTestCase
{
    public static function protectedPaths(): iterable
    {
        yield 'endpoint' => ['POST', '/mcp'];
        yield 'server.json' => ['GET', '/mcp/server.json'];
        yield 'manifest' => ['GET', '/mcp/manifest/claude.json'];
        yield 'versioned manifest' => ['GET', '/mcp/v1/manifest/cursor.json'];
    }

    #[DataProvider('protectedPaths')]
    public function testMissingTokenIs401(string $method, string $path): void
    {
        $response = $method === 'POST' ? $this->post($path, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], token: null) : $this->get($path, token: null);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['error' => 'Unauthorized. Token not found'], $this->json($response));
    }

    #[DataProvider('protectedPaths')]
    public function testInvalidTokenIs403(string $method, string $path): void
    {
        $response = $method === 'POST' ? $this->post($path, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], token: 'wrong') : $this->get($path, token: 'wrong');

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(['error' => 'Forbidden. Invalid token'], $this->json($response));
    }

    public function testValidTokenPasses(): void
    {
        self::assertNotSame('', $this->initialize());
        self::assertSame(200, $this->get('/mcp/server.json')->getStatusCode());
    }

    public function testPreflightPassesWithoutToken(): void
    {
        $response = $this->send(Request::create('http://localhost/mcp', 'OPTIONS', server: [
            'HTTP_ORIGIN' => 'https://client.test', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertStringContainsString('Ufo-RPC-Token', (string) $response->headers->get('Access-Control-Allow-Headers'));
        self::assertStringContainsString('Mcp-Session-Id', (string) $response->headers->get('Access-Control-Allow-Headers'));
    }

    public static function notFoundPaths(): iterable
    {
        yield 'disabled group' => ['/mcp/off'];
        yield 'unknown API version' => ['/mcp/v9'];
        yield 'group without methods' => ['/mcp/empty'];
        yield 'server.json of a disabled group' => ['/mcp/off/server.json'];
        yield 'manifest of an unknown version' => ['/mcp/v9/manifest/claude.json'];
    }

    #[DataProvider('notFoundPaths')]
    public function testUnavailableIs404EvenWithoutToken(string $path): void
    {
        foreach ([TestKernel::TOKEN, null] as $token) {
            $response = str_contains($path, '.json') ? $this->get($path, token: $token) : $this->post($path, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], token: $token);
            self::assertSame(404, $response->getStatusCode(), $path);
            self::assertSame('', (string) $response->getContent());
        }
    }

    public function testPreflightOfUnavailableGroupIs404(): void
    {
        $response = $this->send(Request::create('http://localhost/mcp/off', 'OPTIONS', server: [
            'HTTP_ORIGIN' => 'https://client.test', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGroupNeedsNoConfigEntry(): void
    {
        TestKernel::$mcpConfig = [];

        self::assertNotSame('', $this->initialize('/mcp'));
        self::assertNotSame('', $this->initialize('/mcp/internal'));
    }

    public function testCustomAvailabilityChecker(): void
    {
        TestKernel::$serviceOverrides = [McpAvailabilityCheckerInterface::class => InternalOffAvailabilityChecker::class];

        self::assertSame(404, $this->post('/mcp/internal', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->getStatusCode());
        self::assertNotSame('', $this->initialize('/mcp'));
        // "off" is disabled in the config, but the custom checker replaces the config-based one.
        self::assertNotSame('', $this->initialize('/mcp/off'));
    }
}
