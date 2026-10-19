<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Unit\DocAdapters;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Ufo\McpBundle\ConfigService\McpConfig;
use Ufo\McpBundle\ConfigService\McpGroupConfig;
use Ufo\McpBundle\DependencyInjection\Configuration;
use Ufo\McpBundle\DocAdapters\Outputs\ServerJsonAdapter;

final class ServerJsonAdapterTest extends TestCase
{
    private function group(array $serverInfo = []): McpGroupConfig
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['mcp_server_info' => $serverInfo]]);

        return (new McpConfig($config))->group('mcp');
    }

    public function testFullDocument(): void
    {
        $document = (new ServerJsonAdapter())->adapt($this->group([
            'name' => 'io.test/app', 'title' => 'App', 'description' => 'Test server',
            'icons' => [['src' => 'https://cdn.test/i.png', 'sizes' => ['32x32']], ['src' => 'https://cdn.test/i.svg', 'theme' => 'dark']],
        ]), 'https://api.test/mcp', 'v3');

        self::assertSame([
            '$schema' => 'https://static.modelcontextprotocol.io/schemas/2025-12-11/server.schema.json',
            'name' => 'io.test/app',
            'title' => 'App',
            'description' => 'Test server',
            'version' => 'v3',
            'websiteUrl' => 'https://api.test',
            'icons' => [['src' => 'https://cdn.test/i.png', 'sizes' => ['32x32']], ['src' => 'https://cdn.test/i.svg', 'theme' => 'dark']],
            'remotes' => [['type' => 'streamable-http', 'url' => 'https://api.test/mcp']],
        ], $document);
    }

    public function testUnsetFieldsAreLeftOut(): void
    {
        self::assertSame(
            ['$schema', 'version', 'websiteUrl', 'remotes'],
            array_keys((new ServerJsonAdapter())->adapt($this->group(), 'http://localhost/mcp', 'v1')),
        );
    }

    public static function websiteUrls(): iterable
    {
        yield 'without port' => ['https://api.test/mcp/internal', 'https://api.test'];
        yield 'with port' => ['http://localhost:8080/mcp', 'http://localhost:8080'];
        yield 'not an absolute URL' => ['/mcp', null];
    }

    #[DataProvider('websiteUrls')]
    public function testWebsiteUrlIsTheOriginOfTheEndpoint(string $endpointUrl, ?string $expected): void
    {
        self::assertSame($expected, (new ServerJsonAdapter())->adapt($this->group(), $endpointUrl, 'v1')['websiteUrl'] ?? null);
    }
}
