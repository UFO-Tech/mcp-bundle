<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Availability;

/** Whether the MCP endpoint answers requests at all, for a given group (each group's "enabled" can override the bundle-wide default — see Ufo\McpBundle\ConfigService\McpGroupConfig). Override this (DI decorator/alias) for a source other than static config — a feature-flag service, a per-tenant switch, etc. */
interface McpAvailabilityCheckerInterface
{
    public function isEnabled(string $group): bool;
}
