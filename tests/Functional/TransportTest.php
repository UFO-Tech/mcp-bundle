<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use Ufo\McpBundle\Security\McpAccessPolicyInterface;
use Ufo\McpBundle\Tests\Fixtures\Services\RestrictedAccessPolicy;
use Ufo\McpBundle\Tests\Fixtures\TestKernel;

/**
 * The SDK Streamable HTTP transport as this bundle sets it up: body limit, CORS, DNS-rebinding protection, methods.
 */
final class TransportTest extends McpKernelTestCase
{
    public function testBodyLimitOfTheGroup(): void
    {
        $body = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping', 'params' => ['pad' => str_repeat('a', 3000)]];

        $internal = $this->post('/mcp/internal', $body);
        self::assertSame(413, $internal->getStatusCode());
        self::assertSame('Request body exceeds the maximum allowed size of 2048 bytes.', $this->json($internal)['error']['message']);

        // The bundle-wide default (4 MiB) for a group without its own limit.
        self::assertNotSame(413, $this->post('/mcp', $body)->getStatusCode());
    }

    public function testDefaultPolicyAllowsAnyOriginAndHost(): void
    {
        $response = $this->post('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], ['Origin' => 'https://anything.test', 'Host' => 'anything.test']);

        self::assertNotSame(403, $response->getStatusCode());
        self::assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testCustomPolicyRestrictsHostsAndOrigins(): void
    {
        TestKernel::$serviceOverrides = [McpAccessPolicyInterface::class => RestrictedAccessPolicy::class];

        $foreignHost = $this->send($this->initializeRequest('http://evil.test/mcp'));
        self::assertSame(403, $foreignHost->getStatusCode());

        $allowed = $this->send($this->initializeRequest('http://mcp.allowed.test/mcp', 'https://app.allowed.test'));
        self::assertSame(200, $allowed->getStatusCode());
        self::assertSame('https://app.allowed.test', $allowed->headers->get('Access-Control-Allow-Origin'));

        // With an Origin header the SDK checks the Origin's host, not the Host header.
        $foreignOrigin = $this->send($this->initializeRequest('http://mcp.allowed.test/mcp', 'https://evil.test'));
        self::assertSame(403, $foreignOrigin->getStatusCode());
        self::assertSame('Forbidden: Invalid Origin header.', (string) $foreignOrigin->getContent());
    }

    public function testNoStandaloneServerStream(): void
    {
        $response = $this->get('/mcp', ['Accept' => 'text/event-stream']);

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('POST, DELETE, OPTIONS', $response->headers->get('Allow'));
    }

    private function initializeRequest(string $url, ?string $origin = null): Request
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream', 'HTTP_UFO_RPC_TOKEN' => TestKernel::TOKEN];
        if ($origin !== null) {
            $server['HTTP_ORIGIN'] = $origin;
        }

        return Request::create($url, 'POST', server: $server, content: json_encode([
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
            'params' => ['protocolVersion' => self::LEGACY_VERSION, 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'phpunit', 'version' => '1']],
        ]));
    }
}
