<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Services;

use LogicException;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\McpBundle\Attribute\McpTool;

final class McpServiceMap
{
    public function __construct(
        private readonly ServiceMap $serviceMap,
    ) {}

    /** @return array<string, Service> Tool name to RPC service, for $version and the current request's group (ServiceMap itself already filters by group via its own ApiGroupContext). */
    public function getServices(string $version): array
    {
        $tools = [];
        foreach ($this->serviceMap->getServices($version) as $service) {
            $attribute = $service->getAttrCollection()->getAttribute(McpTool::class);
            if ($attribute === null) {
                continue;
            }
            $name = $service->getName();
            if (!preg_match('/^[a-zA-Z0-9_.-]{1,128}$/D', $name)) {
                throw new LogicException(sprintf('RPC method "%s" cannot be exposed as an MCP tool: its name does not match the MCP tool name pattern.', $name));
            }
            if (isset($tools[$name])) {
                throw new LogicException(sprintf('Duplicate MCP tool name "%s".', $name));
            }
            $tools[$name] = $service;
        }

        ksort($tools);
        return $tools;
    }
}
