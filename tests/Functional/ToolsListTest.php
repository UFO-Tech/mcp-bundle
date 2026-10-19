<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Functional;

/**
 * tools/list: exposure by API group and version, texts from the core translations, input/output schemas, annotations.
 */
final class ToolsListTest extends McpKernelTestCase
{
    private const array CATALOG_TOOLS = ['Catalog.ping', 'Catalog.stats', 'Catalog.item', 'Catalog.byColor', 'Catalog.boom', 'Catalog.cached', 'Catalog.untyped'];

    public function testToolsAreTheMethodsOfTheGroup(): void
    {
        self::assertSame(self::CATALOG_TOOLS, array_keys($this->listTools($this->initialize('/mcp'), '/mcp')));
        self::assertSame(
            [...self::CATALOG_TOOLS, 'Internal.secret'],
            array_keys($this->listTools($this->initialize('/mcp/internal'), '/mcp/internal')),
        );
    }

    public function testToolsOfTheRouteVersion(): void
    {
        $tools = $this->listTools($this->initialize('/mcp/v2'), '/mcp/v2');

        // v2 replaces Catalog.ping and keeps the other v1 methods (core versioning).
        self::assertSame(self::CATALOG_TOOLS, array_keys($tools));
        // The v2 implementation (no #[McpTool]); the texts are translated by method name, whatever the version.
        self::assertArrayNotHasKey('annotations', $tools['Catalog.ping']);
        self::assertSame([], $tools['Catalog.ping']['inputSchema']['properties']);
    }

    public function testTextsComeFromGroupTranslationsOverBaseOnes(): void
    {
        $ping = $this->listTools($this->initialize('/mcp'), '/mcp', ['Accept-Language' => 'uk-UA'])['Catalog.ping'];

        self::assertSame('Пінг', $ping['title'], 'base rpc.uk.yaml');
        self::assertSame('Опис для MCP', $ping['description'], 'group rpc/mcp.uk.yaml');
    }

    public function testOtherGroupGetsOnlyBaseTranslation(): void
    {
        $ping = $this->listTools($this->initialize('/mcp/internal'), '/mcp/internal', ['Accept-Language' => 'uk'])['Catalog.ping'];

        self::assertSame('Пінг', $ping['title']);
        self::assertSame('Опис з бази', $ping['description']);
    }

    public function testLanguageIsNegotiatedAndReported(): void
    {
        $sessionId = $this->initialize('/mcp');
        $response = $this->post('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], ['Mcp-Session-Id' => $sessionId, 'Accept-Language' => 'en-US,en;q=0.9']);
        $ping = $this->json($response)['result']['tools'][0];

        self::assertSame(['Ping', 'Base description'], [$ping['title'], $ping['description']]);
        self::assertSame('en', $response->headers->get('Content-Language'));
        self::assertContains('Accept-Language', $response->getVary());
    }

    public function testWithoutTranslationTheTextsComeFromCode(): void
    {
        $stats = $this->listTools($this->initialize('/mcp'), '/mcp', ['Accept-Language' => 'uk'])['Catalog.stats'];

        self::assertSame('Catalog.stats', $stats['title'], 'no title anywhere: the tool name');
        self::assertSame('Associative array result.', $stats['description'], 'PHPDoc');
    }

    public function testInputSchema(): void
    {
        $tools = $this->listTools($this->initialize('/mcp'), '/mcp');

        self::assertSame([
            'type' => 'object',
            'properties' => ['who' => ['type' => 'string', 'description' => 'Who is pinging', 'default' => 'x']],
            'additionalProperties' => false,
        ], $tools['Catalog.ping']['inputSchema']);

        $item = $tools['Catalog.item']['inputSchema'];
        self::assertSame(['id'], $item['required']);
        self::assertSame(['type' => 'integer'], $item['properties']['id']);

        $byColor = $tools['Catalog.byColor']['inputSchema'];
        self::assertArrayNotHasKey('required', $byColor);
        self::assertSame(['$ref' => '#/$defs/Color', 'default' => 'red'], $byColor['properties']['color']);
        self::assertSame(['red', 'blue'], $byColor['$defs']['Color']['enum']);
    }

    public function testEmptyInputSchemaPropertiesIsAJsonObject(): void
    {
        $response = $this->post('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], ['Mcp-Session-Id' => $this->initialize('/mcp')]);

        self::assertStringContainsString('"name":"Catalog.stats","title":"Catalog.stats","inputSchema":{"type":"object","properties":{}', (string) $response->getContent());
        self::assertStringNotContainsString('"properties":[]', (string) $response->getContent());
    }

    public function testOutputSchema(): void
    {
        $tools = $this->listTools($this->initialize('/mcp'), '/mcp');

        self::assertSame(
            ['type' => 'object', 'properties' => ['result' => ['type' => 'string']], 'required' => ['result']],
            $tools['Catalog.ping']['outputSchema'],
        );

        // A PHP array may be a JSON list or a JSON object.
        self::assertSame(['array', 'object'], $tools['Catalog.stats']['outputSchema']['properties']['result']['type']);

        $item = $tools['Catalog.item']['outputSchema'];
        self::assertSame(['$ref' => '#/$defs/ItemDto'], $item['properties']['result']);
        self::assertSame(['id', 'name'], $item['$defs']['ItemDto']['required']);
        self::assertSame(['$ref' => '#/$defs/Color', 'default' => 'red'], $item['$defs']['ItemDto']['properties']['color']);
        self::assertArrayHasKey('Color', $item['$defs']);
        self::assertStringNotContainsString('#/components/schemas', json_encode($tools, JSON_THROW_ON_ERROR));

        self::assertArrayNotHasKey('outputSchema', $tools['Catalog.untyped'], 'no return type: no schema');

        // ItemDto[]: a list of $refs, each array node also accepting an object.
        $list = $tools['Catalog.byColor']['outputSchema']['properties']['result'];
        self::assertSame(['array', 'object'], $list['type']);
        self::assertSame(['$ref' => '#/$defs/ItemDto'], $list['items']);
    }

    public function testAnnotationsFromMcpTool(): void
    {
        $tools = $this->listTools($this->initialize('/mcp'), '/mcp');

        self::assertSame(['readOnlyHint' => true, 'idempotentHint' => true], $tools['Catalog.ping']['annotations']);
        self::assertArrayNotHasKey('annotations', $tools['Catalog.stats']);
    }

    public function testInvalidToolNameFailsTheGroupLoudly(): void
    {
        $response = $this->post('/mcp/bad', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
            'params' => ['protocolVersion' => self::LEGACY_VERSION, 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'phpunit', 'version' => '1']],
        ]);

        self::assertSame(500, $response->getStatusCode());
        // The other groups are not affected.
        self::assertNotSame('', $this->initialize('/mcp'));
    }

    public function testInvalidToolNameIsExplainedInDebug(): void
    {
        self::bootKernel(['debug' => true]);
        $response = $this->post('/mcp/bad', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
            'params' => ['protocolVersion' => self::LEGACY_VERSION, 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'phpunit', 'version' => '1']],
        ]);

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('RPC method \\"Bad:ping\\" cannot be exposed as an MCP tool', (string) $response->getContent());
    }
}
