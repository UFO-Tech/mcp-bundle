<?php

declare(strict_types=1);

namespace Ufo\McpBundle\ConfigService;

/**
 * Per-group settings that are genuinely MCP-specific. Authentication is not one of
 * them: the group name here is the same "api_group" the app's routing already feeds
 * into Ufo\JsonRpcBundle\Server\ApiGroupContext for REST/RPC, so token validation
 * stays entirely with the core bundle's own ufo_json_rpc.security.providers
 * (keyed by that same api_group) — one shared security model, not a parallel one.
 *
 * There is deliberately no "api_version" here: which RPC service-map version a given
 * request serves comes from the "{ver}" route segment (default "v1"), the same role it
 * plays in rest-bundle/json-rpc-bundle — see Ufo\McpBundle\Controller\McpController.
 * Callers that need "the version this request is on" (Ufo\McpBundle\Services\McpServerFactory,
 * Ufo\McpBundle\DocAdapters\Outputs\ServerJsonAdapter) take it as a parameter instead.
 *
 * Every field left unset in this group's own config falls back to $base (the bundle-wide
 * McpConfig) — each one is independently overridable per group.
 */
final readonly class McpGroupConfig
{
    public bool $enabled;

    public ?string $instructions;

    public int $maxBodyBytes;

    public string $sessionDirectory;

    public int $sessionTtl;

    /** This group's server.json info: its own "mcp_server_info" overrides layered on top of the bundle-wide default. Carries no "version" at all — see McpServerInfoConfig. */
    public McpServerInfoConfig $serverInfo;

    public function __construct(public string $name, array $config, McpConfig $base)
    {
        $this->enabled = $config['enabled'] ?? $base->enabled;
        $this->instructions = $config['instructions'] ?? $base->instructions;
        $this->maxBodyBytes = $config['max_body_bytes'] ?? $base->maxBodyBytes;
        $this->sessionDirectory = $config['session_directory'] ?? $base->sessionDirectory;
        $this->sessionTtl = $config['session_ttl'] ?? $base->sessionTtl;
        $this->serverInfo = McpServerInfoConfig::fromConfig($config['mcp_server_info'])->overriding($base->serverInfo);
    }
}
