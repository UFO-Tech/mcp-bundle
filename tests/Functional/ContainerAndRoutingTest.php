<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\McpBundle\Availability\DefaultMcpAvailabilityChecker;
use Ufo\McpBundle\Availability\McpAvailabilityCheckerInterface;
use Ufo\McpBundle\ConfigService\McpConfig;
use Ufo\McpBundle\Routing\McpRouteImport;
use Ufo\McpBundle\Security\DefaultMcpAccessPolicy;
use Ufo\McpBundle\Security\McpAccessPolicyInterface;
use Ufo\McpBundle\Server\McpHandler;
use Ufo\McpBundle\Tests\Fixtures\TestKernel;
use Ufo\McpBundle\Tool\RpcToolExecutor;

/**
 * The bundle in an application: the container compiles with any valid config, every route import gets its own names.
 */
final class ContainerAndRoutingTest extends McpKernelTestCase
{
    public static function configs(): iterable
    {
        yield 'no config at all' => [[]];
        yield 'defaults of the install template' => [['enabled' => true, 'instructions' => null, 'max_body_bytes' => 4194304, 'session_ttl' => 3600]];
        yield 'groups with explicit nulls' => [['groups' => ['mcp' => ['enabled' => null, 'instructions' => null, 'max_body_bytes' => null, 'session_ttl' => null]]]];
    }

    #[DataProvider('configs')]
    public function testContainerCompiles(array $config): void
    {
        TestKernel::$mcpConfig = $config;
        self::bootKernel();
        $container = self::getContainer();

        self::assertInstanceOf(McpConfig::class, $container->get(McpConfig::class));
        self::assertInstanceOf(McpHandler::class, $container->get(McpHandler::class));
        self::assertInstanceOf(RpcToolExecutor::class, $container->get(RpcToolExecutor::class));
        self::assertInstanceOf(DefaultMcpAccessPolicy::class, $container->get(McpAccessPolicyInterface::class));
        self::assertInstanceOf(DefaultMcpAvailabilityChecker::class, $container->get(McpAvailabilityCheckerInterface::class));
    }

    public function testEachImportGetsItsOwnRouteNames(): void
    {
        self::bootKernel();
        $routes = self::getContainer()->get('router')->getRouteCollection();

        foreach (['mcp' => '/mcp', 'internal' => '/mcp/internal'] as $group => $prefix) {
            $endpoint = $routes->get($group.'_ufo_mcp_endpoint');
            self::assertNotNull($endpoint, $group);
            self::assertSame($prefix.'/{ver}', $endpoint->getPath());
            self::assertSame('v1', $endpoint->getDefault('ver'));
            self::assertSame($group, $endpoint->getDefault(ApiGroupContext::ROUTE_GROUP));
            self::assertSame($group.'_', $endpoint->getDefault(McpRouteImport::NAME_PREFIX_ATTR));
            self::assertSame(['GET', 'POST', 'DELETE', 'OPTIONS'], $endpoint->getMethods());

            foreach (['manifest', 'manifest_ver', 'server_json', 'server_json_ver'] as $name) {
                self::assertNotNull($routes->get($group.'_ufo_mcp_'.$name), $group.'_ufo_mcp_'.$name);
            }
        }
        self::assertNull($routes->get('ufo_mcp_endpoint'), 'no unprefixed name that a second import would overwrite');
    }

    public function testUrlGeneration(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');

        self::assertSame('/mcp/internal', $router->generate('internal_ufo_mcp_endpoint'));
        self::assertSame('/mcp/v2', $router->generate('mcp_ufo_mcp_endpoint', ['ver' => 'v2']));
        self::assertSame('/mcp/internal/manifest/claude.json', $router->generate('internal_ufo_mcp_manifest', ['aiName' => 'claude']));
    }
}
