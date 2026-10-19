<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Unit\ConfigService;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Ufo\McpBundle\ConfigService\McpConfig;
use Ufo\McpBundle\DependencyInjection\Configuration;

final class McpConfigTest extends TestCase
{
    private function config(array $config): McpConfig
    {
        return new McpConfig((new Processor())->processConfiguration(new Configuration(), [$config]));
    }

    public function testBundleWideValues(): void
    {
        $config = $this->config(['enabled' => false, 'instructions' => 'Hi', 'max_body_bytes' => 10, 'session_ttl' => 20]);

        self::assertFalse($config->enabled);
        self::assertSame('Hi', $config->instructions);
        self::assertSame(10, $config->maxBodyBytes);
        self::assertSame(20, $config->sessionTtl);
        self::assertSame([], $config->groups);
    }

    public function testGroupWithoutEntryRunsOnBundleWideSettings(): void
    {
        $group = $this->config(['instructions' => 'Hi', 'session_ttl' => 20, 'mcp_server_info' => ['name' => 'io.test/app']])->group('anything');

        self::assertSame('anything', $group->name);
        self::assertTrue($group->enabled);
        self::assertSame('Hi', $group->instructions);
        self::assertSame(4194304, $group->maxBodyBytes);
        self::assertSame(20, $group->sessionTtl);
        self::assertSame('io.test/app', $group->serverInfo->name);
    }

    public function testEachGroupFieldOverridesOnItsOwn(): void
    {
        $config = $this->config([
            'enabled' => true, 'instructions' => 'Base', 'max_body_bytes' => 100, 'session_ttl' => 200,
            'mcp_server_info' => ['name' => 'io.test/app', 'title' => 'App', 'description' => 'Base server'],
            'groups' => ['internal' => [
                'enabled' => false, 'max_body_bytes' => 50,
                'mcp_server_info' => ['name' => 'io.test/internal'],
            ]],
        ]);
        $group = $config->group('internal');

        self::assertSame($config->groups['internal'], $group, 'the configured group itself');
        self::assertFalse($group->enabled);
        self::assertSame('Base', $group->instructions);
        self::assertSame(50, $group->maxBodyBytes);
        self::assertSame(200, $group->sessionTtl);
        self::assertSame('io.test/internal', $group->serverInfo->name);
        self::assertSame('App', $group->serverInfo->title);
        self::assertSame('Base server', $group->serverInfo->description);
    }

    public function testExplicitNullInheritsAndFalseOverrides(): void
    {
        $config = $this->config(['enabled' => false, 'groups' => ['a' => ['enabled' => null], 'b' => ['enabled' => true]]]);

        self::assertFalse($config->group('a')->enabled);
        self::assertTrue($config->group('b')->enabled);
    }
}
