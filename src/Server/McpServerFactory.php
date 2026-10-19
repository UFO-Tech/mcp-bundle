<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Server;

use Mcp\Server;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Ufo\JsonRpcBundle\ConfigService\RpcCacheConfig;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\McpBundle\ConfigService\McpConfig;
use Ufo\McpBundle\Server\Session\RpcCacheSessionStore;
use Ufo\McpBundle\Tool\RpcToolExecutor;
use Ufo\McpBundle\Tool\RpcToolHandler;
use Ufo\McpBundle\Tool\ToolSchemaFactory;

final class McpServerFactory
{
    /** Server identity comes entirely from "ufo_mcp.mcp_server_info" (no separate "server_name" config) — this is only the last-resort fallback when "name" is left unset there too. */
    private const string DEFAULT_SERVER_NAME = 'ufo-rpc';

    private const string SESSION_PREFIX = 'ufo_mcp_';

    public function __construct(
        private readonly ServiceMap $serviceMap,
        private readonly ToolSchemaFactory $schemaFactory,
        private readonly RpcToolExecutor $executor,
        private readonly McpConfig $config,
        private readonly ApiGroupContext $apiGroupContext,
        /**
         * The json-rpc-bundle cache pool (ufo_json_rpc.cache.pool): a local filesystem pool
         * by default, a shared one (e.g. Redis) when the app configures it — so legacy MCP
         * sessions follow the same storage as the RPC response cache, on every host.
         */
        #[Autowire(service: RpcCacheConfig::POOL_SERVICE)]
        private readonly CacheItemPoolInterface $sessionPool,
    ) {}

    /**
     * $version is the RPC service-map version resolved from the request's own "{ver}" route segment (Ufo\McpBundle\Controller\McpController), not a static per-group config value.
     * $locale is the language negotiated for tool titles/descriptions (null — no translations).
     * @throws ReflectionException
     */
    public function create(string $version, ?string $locale = null): Server
    {
        $group = $this->config->group($this->apiGroupContext->getGroup());
        $builder = Server::builder()
            ->setServerInfo(
                name: $group->serverInfo->name ?? self::DEFAULT_SERVER_NAME,
                version: $version,
                title: $group->serverInfo->title,
            )
            ->setInstructions($group->instructions)
            ->setSession(new RpcCacheSessionStore(
                $this->sessionPool,
                self::SESSION_PREFIX.$group->name.'_',
                $group->sessionTtl,
            ));

        foreach ($this->serviceMap->getServices($version) as $name => $service) {
            $builder->add(
                $this->schemaFactory->create($name, $service, $version, $locale),
                new RpcToolHandler($service, $this->executor, $version)
            );
        }

        return $builder->build();
    }
}
