<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Availability;

use Ufo\McpBundle\ConfigService\McpConfig;

/** Reads "ufo_mcp.groups.<group>.enabled" (falling back to the bundle-wide "ufo_mcp.enabled") as-is — which already supports "%env(bool:MCP_ENABLED)%" for an env-driven on/off switch without needing a custom implementation. */
final readonly class DefaultMcpAvailabilityChecker implements McpAvailabilityCheckerInterface
{
    public function __construct(private McpConfig $config) {}

    public function isEnabled(string $group): bool
    {
        return $this->config->group($group)->enabled;
    }
}
