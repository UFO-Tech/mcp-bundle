<?php

declare(strict_types=1);

namespace Ufo\McpBundle\ConfigService;

use LogicException;

/** Typed representation of the processed "ufo_mcp" config, mirroring the role of Ufo\JsonRpcBundle\ConfigService\RpcMainConfig for this bundle's own options. */
final readonly class McpConfig
{
    public bool $enabled;

    public ?string $instructions;

    public int $maxBodyBytes;

    public string $sessionDirectory;

    public int $sessionTtl;

    /**
     * Bundle-wide server.json defaults — the single source of truth for server identity
     * ("name"/"title"), not duplicated by a separate "server_name" config. No fallback
     * to "instructions" either: "description" is either set explicitly here/per-group or
     * stays null. "version" has no bundle-wide default — see McpGroupConfig, which falls
     * back to the request's own "{ver}" route segment instead, the same role it plays in
     * rest-bundle/json-rpc-bundle.
     */
    public McpServerInfoConfig $serverInfo;

    /** @var array<string, McpGroupConfig> */
    public array $groups;

    public function __construct(array $options)
    {
        $this->enabled = $options['enabled'];
        $this->instructions = $options['instructions'];
        $this->maxBodyBytes = $options['max_body_bytes'];
        $this->sessionDirectory = $options['session_directory'];
        $this->sessionTtl = $options['session_ttl'];

        $this->serverInfo = McpServerInfoConfig::fromConfig($options['mcp_server_info']);

        $groups = [];
        foreach ($options['groups'] as $name => $group) {
            $groups[$name] = new McpGroupConfig($name, $group, $this);
        }
        $this->groups = $groups;
    }

    public function group(string $name): McpGroupConfig
    {
        return $this->groups[$name]
            ?? throw new LogicException(sprintf('MCP group "%s" is not configured under "ufo_mcp.groups".', $name));
    }
}
