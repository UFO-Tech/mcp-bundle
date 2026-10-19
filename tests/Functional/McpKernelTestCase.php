<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Ufo\McpBundle\Tests\Fixtures\TestKernel;

use function json_decode;
use function json_encode;
use function restore_exception_handler;

use const JSON_THROW_ON_ERROR;

/**
 * Sends real HTTP requests through the kernel (handle + terminate), the way the web server does.
 */
abstract class McpKernelTestCase extends KernelTestCase
{
    protected const string LEGACY_VERSION = '2025-06-18';
    protected const string MODERN_VERSION = '2026-07-28';

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel($options['environment'] ?? 'test', $options['debug'] ?? false);
    }

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        TestKernel::$mcpConfig = null;
        TestKernel::$serviceOverrides = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        TestKernel::$mcpConfig = null;
        TestKernel::$serviceOverrides = [];
        restore_exception_handler();
    }

    protected function send(Request $request): Response
    {
        $kernel = self::$kernel ?? self::bootKernel();
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }

    /** @param array<string, string> $headers HTTP header name => value */
    protected function post(string $path, mixed $body, array $headers = [], ?string $token = TestKernel::TOKEN): Response
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'];
        if ($token !== null) {
            $server['HTTP_UFO_RPC_TOKEN'] = $token;
        }
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->send(Request::create('http://localhost'.$path, 'POST', server: $server, content: json_encode($body, JSON_THROW_ON_ERROR)));
    }

    /** @param array<string, string> $headers */
    protected function get(string $path, array $headers = [], ?string $token = TestKernel::TOKEN): Response
    {
        $server = [];
        if ($token !== null) {
            $server['HTTP_UFO_RPC_TOKEN'] = $token;
        }
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->send(Request::create('http://localhost'.$path, 'GET', server: $server));
    }

    /** Legacy handshake (initialize + notifications/initialized); returns the Mcp-Session-Id. */
    protected function initialize(string $path = '/mcp', array $headers = []): string
    {
        $response = $this->post($path, [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
            'params' => ['protocolVersion' => self::LEGACY_VERSION, 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'phpunit', 'version' => '1']],
        ], $headers);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $sessionId = (string) $response->headers->get('Mcp-Session-Id');
        self::assertNotSame('', $sessionId);

        $this->post($path, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], ['Mcp-Session-Id' => $sessionId]);

        return $sessionId;
    }

    /** One JSON-RPC request in an initialized legacy session; returns the decoded JSON-RPC response. */
    protected function call(string $sessionId, string $method, array $params = [], string $path = '/mcp', array $headers = []): array
    {
        $response = $this->post($path, [
            'jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => $params ?: new \stdClass(),
        ], ['Mcp-Session-Id' => $sessionId] + $headers);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $this->json($response);
    }

    protected function callTool(string $sessionId, string $tool, array $arguments = [], string $path = '/mcp'): array
    {
        return $this->call($sessionId, 'tools/call', ['name' => $tool, 'arguments' => $arguments ?: new \stdClass()], $path)['result'];
    }

    /** @return array<string, array> tool name => tool */
    protected function listTools(string $sessionId, string $path = '/mcp', array $headers = []): array
    {
        $tools = [];
        foreach ($this->call($sessionId, 'tools/list', [], $path, $headers)['result']['tools'] as $tool) {
            $tools[$tool['name']] = $tool;
        }

        return $tools;
    }

    protected function json(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
