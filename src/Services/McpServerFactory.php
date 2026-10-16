<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Services;

use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\McpBundle\ConfigService\McpConfig;

final class McpServerFactory
{
    /** Server identity comes entirely from "ufo_mcp.mcp_server_info" (no separate "server_name" config) — this is only the last-resort fallback when "name" is left unset there too. */
    private const string DEFAULT_SERVER_NAME = 'ufo-rpc';

    public function __construct(
        private readonly McpServiceMap $serviceMap,
        private readonly ToolSchemaFactory $schemaFactory,
        private readonly RpcToolExecutor $executor,
        private readonly McpConfig $config,
        private readonly ApiGroupContext $apiGroupContext,
    ) {}

    /** $version is the RPC service-map version resolved from the request's own "{ver}" route segment (Ufo\McpBundle\Controller\McpController), not a static per-group config value. */
    public function create(string $version): Server
    {
        $group = $this->config->group($this->apiGroupContext->getGroup());
        $builder = Server::builder()
            ->setServerInfo(
                name: $group->serverInfo->name ?? self::DEFAULT_SERVER_NAME,
                version: $version,
                title: $group->serverInfo->title,
            )
            ->setInstructions($group->instructions)
            ->setSession(new FileSessionStore($group->sessionDirectory, $group->sessionTtl));

        foreach ($this->serviceMap->getServices($version) as $name => $service) {
            $builder->add($this->schemaFactory->create($name, $service), new RpcToolHandler($service, $this->executor, $version));
        }

        return $builder->build();
    }
}
