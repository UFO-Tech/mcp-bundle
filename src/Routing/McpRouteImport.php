<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Routing;

use Ufo\JsonRpcBundle\Routing\IApiRouteImport;

/**
 * Registers this bundle's config/router.yaml with the core RPC YAML loader: every
 * import of it (one per MCP group, "defaults: { api_group: ... }") gets its own route
 * names ("<group>_ufo_mcp_endpoint", ...) and the "<group>_<name_prefix>" value in
 * self::NAME_PREFIX_ATTR, so several MCP mounts no longer overwrite each other.
 */
final class McpRouteImport implements IApiRouteImport
{
    public const string NAME_PREFIX_ATTR = '_mcp_name_prefix';

    public function getResource(): string
    {
        return __DIR__.'/../../config/router.yaml';
    }

    public function getRouteNamePrefixAttribute(): string
    {
        return self::NAME_PREFIX_ATTR;
    }
}
