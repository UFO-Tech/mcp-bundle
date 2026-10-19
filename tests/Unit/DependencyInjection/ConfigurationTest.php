<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Ufo\McpBundle\DependencyInjection\Configuration;

final class ConfigurationTest extends TestCase
{
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }

    public function testDefaults(): void
    {
        self::assertSame([
            'enabled' => true,
            'instructions' => null,
            'max_body_bytes' => 4194304,
            'session_ttl' => 3600,
            'mcp_server_info' => ['name' => null, 'title' => null, 'description' => null, 'icons' => []],
            'groups' => [],
        ], $this->process([]));
    }

    public function testGroupFieldsDefaultToNullToInherit(): void
    {
        self::assertSame([
            'enabled' => null,
            'instructions' => null,
            'max_body_bytes' => null,
            'session_ttl' => null,
            'mcp_server_info' => ['name' => null, 'title' => null, 'description' => null, 'icons' => []],
        ], $this->process(['groups' => ['mcp' => null]])['groups']['mcp']);
    }

    public function testFullConfig(): void
    {
        $config = $this->process([
            'enabled' => false,
            'instructions' => 'Use it.',
            'max_body_bytes' => 1024,
            'session_ttl' => 60,
            'mcp_server_info' => [
                'name' => 'io.test/app',
                'icons' => [['src' => 'https://cdn.test/i.png', 'mime_type' => 'image/png', 'sizes' => ['32x32'], 'theme' => 'dark']],
            ],
            'groups' => ['internal' => ['enabled' => true, 'max_body_bytes' => 10, 'mcp_server_info' => ['title' => 'Internal']]],
        ]);

        self::assertFalse($config['enabled']);
        self::assertSame(1024, $config['max_body_bytes']);
        self::assertSame([['src' => 'https://cdn.test/i.png', 'mime_type' => 'image/png', 'sizes' => ['32x32'], 'theme' => 'dark']], $config['mcp_server_info']['icons']);
        self::assertSame(10, $config['groups']['internal']['max_body_bytes']);
        self::assertSame('Internal', $config['groups']['internal']['mcp_server_info']['title']);
    }

    public static function invalidConfigs(): iterable
    {
        yield 'zero body limit' => [['max_body_bytes' => 0]];
        yield 'zero session ttl' => [['session_ttl' => 0]];
        yield 'icon without src' => [['mcp_server_info' => ['icons' => [['mime_type' => 'image/png']]]]];
        yield 'removed session_directory' => [['session_directory' => '/tmp']];
        yield 'removed server_name' => [['server_name' => 'x']];
        yield 'unknown group option' => [['groups' => ['mcp' => ['api_version' => 'v2']]]];
    }

    #[DataProvider('invalidConfigs')]
    public function testInvalidConfig(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process($config);
    }
}
