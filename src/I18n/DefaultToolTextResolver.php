<?php

declare(strict_types=1);

namespace Ufo\McpBundle\I18n;

/** No translator configured in the app (or none wired for MCP): always the fallback. */
final class DefaultToolTextResolver implements ToolTextResolverInterface
{
    public function title(string $toolName, string $fallback): string
    {
        return $fallback;
    }

    public function description(string $toolName, string $fallback): string
    {
        return $fallback;
    }
}
