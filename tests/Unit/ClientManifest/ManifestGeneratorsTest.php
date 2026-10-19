<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Unit\ClientManifest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Container;
use Ufo\McpBundle\ClientManifest\AI\ClaudeCodeManifestGenerator;
use Ufo\McpBundle\ClientManifest\AI\CursorManifestGenerator;
use Ufo\McpBundle\ClientManifest\AI\GeminiManifestGenerator;
use Ufo\McpBundle\ClientManifest\AI\OpenAiManifestGenerator;
use Ufo\McpBundle\ClientManifest\McpClientManifestGeneratorHolder;
use Ufo\McpBundle\ClientManifest\McpClientManifestGeneratorInterface;
use Ufo\McpBundle\ConfigService\McpConfig;
use Ufo\McpBundle\ConfigService\McpGroupConfig;
use Ufo\McpBundle\DependencyInjection\Configuration;

final class ManifestGeneratorsTest extends TestCase
{
    private const string URL = 'https://api.test/mcp/internal';

    private function group(): McpGroupConfig
    {
        return (new McpConfig((new Processor())->processConfiguration(new Configuration(), [[]])))->group('internal');
    }

    public static function generators(): iterable
    {
        yield [new ClaudeCodeManifestGenerator(), 'claude', ['mcpServers' => ['internal' => ['type' => 'http', 'url' => self::URL, 'headers' => ['X-Token' => '${UFO_MCP_TOKEN}']]]]];
        yield [new CursorManifestGenerator(), 'cursor', ['mcpServers' => ['internal' => ['url' => self::URL, 'headers' => ['X-Token' => '${env:UFO_MCP_TOKEN}']]]]];
        yield [new GeminiManifestGenerator(), 'gemini', ['mcpServers' => ['internal' => ['httpUrl' => self::URL, 'headers' => ['X-Token' => '<UFO_MCP_TOKEN>']]]]];
        yield [new OpenAiManifestGenerator(), 'openai', ['type' => 'mcp', 'server_label' => 'internal', 'server_url' => self::URL, 'headers' => ['X-Token' => '<UFO_MCP_TOKEN>'], 'require_approval' => 'always']];
    }

    #[DataProvider('generators')]
    public function testManifest(McpClientManifestGeneratorInterface $generator, string $key, array $expected): void
    {
        self::assertSame($expected, $generator->generate($this->group(), self::URL, 'X-Token'));
    }

    #[DataProvider('generators')]
    public function testTaggedUnderItsKey(McpClientManifestGeneratorInterface $generator, string $key): void
    {
        $tag = (new ReflectionClass($generator))->getAttributes(AutoconfigureTag::class)[0]->newInstance();

        self::assertSame([[McpClientManifestGeneratorHolder::TAG => ['key' => $key]]], $tag->tags);
    }

    public function testHolderFindsByKey(): void
    {
        $locator = new Container();
        $locator->set('claude', $claude = new ClaudeCodeManifestGenerator());
        $holder = new McpClientManifestGeneratorHolder($locator);

        self::assertSame($claude, $holder->get('claude'));
        self::assertNull($holder->get('unknown'));
    }
}
