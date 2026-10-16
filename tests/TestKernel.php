<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Ufo\JsonRpcBundle\UfoJsonRpcBundle;
use Ufo\McpBundle\Tests\Fixtures\Procedures\CatalogProcedure;
use Ufo\McpBundle\Tests\Fixtures\Procedures\CatalogV2Procedure;
use Ufo\McpBundle\Tests\Fixtures\Procedures\PartnerProcedure;
use Ufo\McpBundle\UfoMcpBundle;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly array $options = [])
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new SecurityBundle(), new UfoJsonRpcBundle(), new UfoMcpBundle()];
    }

    public function getProjectDir(): string
    {
        // Must not resolve to this bundle's own checkout: with kernel.debug=true,
        // Symfony's PhpConfigReferenceDumpPass writes a config/reference.php IDE
        // helper into "<project dir>/config/" on every boot, and Kernel's default
        // getProjectDir() would otherwise find this bundle's own composer.json.
        return sys_get_temp_dir().'/ufo-mcp-tests/app';
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ufo-mcp-tests/'.Kernel::VERSION.'/'.getmypid().'/'.md5(serialize($this->options));
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
            'security' => [
                'protected_api' => true, 'protected_doc' => false,
                'clients_tokens' => ['test-token'],
                'providers' => ['partners' => ['api_groups' => ['partners'], 'clients_tokens' => ['partner-token']]],
            ],
            'docs' => ['project_name' => 'test', 'validations' => []],
        ]);
        $container->extension('ufo_mcp', $this->options);
        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('logger', \Psr\Log\NullLogger::class);
        $services->set(CatalogProcedure::class)->public();
        $services->set(PartnerProcedure::class)->public();
        $services->set(CatalogV2Procedure::class)->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/Fixtures/routes.yaml');
    }
}
