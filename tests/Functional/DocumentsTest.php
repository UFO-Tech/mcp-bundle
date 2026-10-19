<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Ufo\McpBundle\Tests\Fixtures\TestKernel;

/**
 * GET .../server.json (MCP Registry) and GET .../manifest/{aiName}.json (client connection configs).
 */
final class DocumentsTest extends McpKernelTestCase
{
    public function testServerJson(): void
    {
        $response = $this->get('/mcp/server.json');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            '$schema' => 'https://static.modelcontextprotocol.io/schemas/2025-12-11/server.schema.json',
            'name' => 'io.test/app',
            'title' => 'Test App',
            'version' => 'v1',
            'websiteUrl' => 'http://localhost',
            'remotes' => [['type' => 'streamable-http', 'url' => 'http://localhost/mcp']],
        ], $this->json($response));
    }

    public function testServerJsonOfAGroupAndVersion(): void
    {
        $document = $this->json($this->get('/mcp/internal/v1/server.json'));

        self::assertSame('io.test/internal', $document['name']);
        self::assertSame('Internal server', $document['description']);
        self::assertSame('Test App', $document['title']);
        self::assertSame('v1', $document['version']);
        self::assertSame([['type' => 'streamable-http', 'url' => 'http://localhost/mcp/internal/v1']], $document['remotes']);

        self::assertSame('v2', $this->json($this->get('/mcp/v2/server.json'))['version']);
    }

    public function testServerJsonIcons(): void
    {
        TestKernel::$mcpConfig = ['mcp_server_info' => ['name' => 'io.test/app', 'icons' => [
            ['src' => 'https://cdn.test/icon.png', 'mime_type' => 'image/png', 'sizes' => ['64x64']],
            ['src' => 'https://cdn.test/icon.svg'],
        ]]];

        self::assertSame([
            ['src' => 'https://cdn.test/icon.png', 'mimeType' => 'image/png', 'sizes' => ['64x64']],
            ['src' => 'https://cdn.test/icon.svg'],
        ], $this->json($this->get('/mcp/server.json'))['icons']);
    }

    public static function manifests(): iterable
    {
        yield 'claude' => ['claude', ['mcpServers' => ['mcp' => [
            'type' => 'http', 'url' => 'http://localhost/mcp', 'headers' => ['Ufo-RPC-Token' => '${UFO_MCP_TOKEN}'],
        ]]]];
        yield 'cursor' => ['cursor', ['mcpServers' => ['mcp' => [
            'url' => 'http://localhost/mcp', 'headers' => ['Ufo-RPC-Token' => '${env:UFO_MCP_TOKEN}'],
        ]]]];
        yield 'gemini' => ['gemini', ['mcpServers' => ['mcp' => [
            'httpUrl' => 'http://localhost/mcp', 'headers' => ['Ufo-RPC-Token' => '<UFO_MCP_TOKEN>'],
        ]]]];
        yield 'openai' => ['openai', [
            'type' => 'mcp', 'server_label' => 'mcp', 'server_url' => 'http://localhost/mcp',
            'headers' => ['Ufo-RPC-Token' => '<UFO_MCP_TOKEN>'], 'require_approval' => 'always',
        ]];
    }

    #[DataProvider('manifests')]
    public function testManifest(string $aiName, array $expected): void
    {
        $response = $this->get('/mcp/manifest/'.$aiName.'.json');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($expected, $this->json($response));
    }

    public function testManifestOfAGroupAndVersion(): void
    {
        self::assertSame(
            ['mcpServers' => ['internal' => ['type' => 'http', 'url' => 'http://localhost/mcp/internal/v1', 'headers' => ['Ufo-RPC-Token' => '${UFO_MCP_TOKEN}']]]],
            $this->json($this->get('/mcp/internal/v1/manifest/claude.json')),
        );
        self::assertSame('http://localhost/mcp/v2', $this->json($this->get('/mcp/v2/manifest/cursor.json'))['mcpServers']['mcp']['url']);
        // "internal" has no v2 methods.
        self::assertSame(404, $this->get('/mcp/internal/v2/manifest/claude.json')->getStatusCode());
    }

    public function testUnknownManifest(): void
    {
        $response = $this->get('/mcp/manifest/unknown.json');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'Unknown MCP client manifest "unknown".'], $this->json($response));
    }
}
