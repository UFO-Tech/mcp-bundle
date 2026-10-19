<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Fixtures;

use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Ufo\JsonRpcBundle\UfoJsonRpcBundle;
use Ufo\McpBundle\Tests\Fixtures\Procedures\BadNameProcedure;
use Ufo\McpBundle\Tests\Fixtures\Procedures\CatalogProcedure;
use Ufo\McpBundle\Tests\Fixtures\Procedures\CatalogV2Procedure;
use Ufo\McpBundle\Tests\Fixtures\Procedures\InternalProcedure;
use Ufo\McpBundle\UfoMcpBundle;

use function getmypid;
use function md5;
use function serialize;
use function sys_get_temp_dir;

/**
 * An application with this bundle: four procedures in the groups "mcp", "internal" and "bad",
 * the routes of config/routes.yaml (one MCP import per group) and RPC translations.
 * The token "test-token" is valid for every group; protected_doc is on.
 */
class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string TOKEN = 'test-token';

    public const array DEFAULT_MCP_CONFIG = [
        'mcp_server_info' => ['name' => 'io.test/app', 'title' => 'Test App'],
        'groups' => [
            'off' => ['enabled' => false],
            'internal' => [
                'instructions' => 'Internal tools.',
                'max_body_bytes' => 2048,
                'mcp_server_info' => ['name' => 'io.test/internal', 'description' => 'Internal server'],
            ],
        ],
    ];

    /** The "ufo_mcp" config of the next kernel (KernelTestCase cannot pass constructor arguments); null — DEFAULT_MCP_CONFIG. */
    public static ?array $mcpConfig = null;

    /** @var array<class-string, class-string> service id => class that replaces it (the app overriding an extension point) */
    public static array $serviceOverrides = [];

    public function __construct(string $environment = 'test', bool $debug = false)
    {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new SecurityBundle(), new UfoJsonRpcBundle(), new UfoMcpBundle()];
    }

    /** Not the bundle checkout: in debug Symfony writes config/reference.php into the project dir. */
    public function getProjectDir(): string
    {
        return sys_get_temp_dir().'/ufo-mcp-tests/app';
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ufo-mcp-tests/'.getmypid().'/'.$this->environment.($this->debug ? '-debug' : '')
            .'/'.md5(serialize([self::$mcpConfig, self::$serviceOverrides]));
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir().'/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false]);
        $container->extension('security', [
            'providers' => ['in_memory' => ['memory' => null]],
            'firewalls' => ['main' => ['lazy' => true]],
        ]);
        $container->extension('ufo_json_rpc', [
            'cache' => [],
            'security' => ['protected_api' => true, 'protected_doc' => true, 'clients_tokens' => [self::TOKEN]],
            'docs' => ['project_name' => 'test', 'validations' => []],
            'translations' => ['paths' => [__DIR__.'/translations']],
        ]);
        $container->extension('ufo_mcp', self::$mcpConfig ?? self::DEFAULT_MCP_CONFIG);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('logger', NullLogger::class);
        foreach ([CatalogProcedure::class, CatalogV2Procedure::class, InternalProcedure::class, BadNameProcedure::class] as $procedure) {
            $services->set($procedure)->public();
        }
        foreach (self::$serviceOverrides as $id => $class) {
            $services->set($id, $class);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/config/routes.yaml');
    }
}
