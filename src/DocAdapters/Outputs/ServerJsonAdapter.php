<?php

declare(strict_types=1);

namespace Ufo\McpBundle\DocAdapters\Outputs;

use Ufo\McpBundle\ConfigService\McpGroupConfig;

use function array_filter;
use function array_map;
use function parse_url;

/**
 * Builds an MCP Registry server.json document for one configured group — the same
 * role OpenRpcAdapter/PostmanAdapter play for the core json-rpc-bundle, but for a
 * publication manifest instead of an API doc. This is unrelated to the MCP protocol's
 * own "initialize" response (handled entirely by mcp/sdk) and unrelated to the
 * per-client connection configs in Ufo\McpBundle\ClientManifest: a server.json is what
 * https://registry.modelcontextprotocol.io expects when this server gets published
 * there, nothing a connecting client reads to use the server.
 *
 * Schema: https://static.modelcontextprotocol.io/schemas/2025-12-11/server.schema.json
 */
final class ServerJsonAdapter
{
    private const string SCHEMA = 'https://static.modelcontextprotocol.io/schemas/2025-12-11/server.schema.json';
    private const string REMOTE_TYPE = 'streamable-http';

    /** $version is the request's resolved "{ver}" route segment — "version" is not configurable in "mcp_server_info", it always reflects the actual RPC service-map version this endpoint is serving. */
    public function adapt(McpGroupConfig $group, string $endpointUrl, string $version): array
    {
        $info = $group->serverInfo;

        return array_filter([
            '$schema' => self::SCHEMA,
            'name' => $info->name,
            'title' => $info->title,
            'description' => $info->description,
            'version' => $version,
            'websiteUrl' => $this->originOf($endpointUrl),
            'icons' => $info->icons !== [] ? array_map(
                static fn(array $icon) => array_filter($icon, static fn($v) => $v !== null && $v !== []),
                $info->icons,
            ) : null,
            'remotes' => [
                [
                    'type' => self::REMOTE_TYPE,
                    'url' => $endpointUrl,
                ],
            ],
        ], static fn($v) => $v !== null);
    }

    /** "websiteUrl" is always derived from the request's own scheme+host — not configurable, since the adapter already knows it and there's no config field for a separate marketing site. */
    private function originOf(string $endpointUrl): ?string
    {
        $urlParts = parse_url($endpointUrl);
        $scheme = $urlParts['scheme'] ?? false;
        $host = $urlParts['host'] ?? false;
        if (!$scheme || !$host) {
            return null;
        }

        return $scheme.'://'.$host.(isset($urlParts['port']) ? ':'.$urlParts['port'] : '');
    }
}
