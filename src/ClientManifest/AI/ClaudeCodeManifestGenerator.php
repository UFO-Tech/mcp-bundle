<?php

declare(strict_types=1);

namespace Ufo\McpBundle\ClientManifest\AI;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\McpBundle\ClientManifest\McpClientManifestGeneratorHolder;
use Ufo\McpBundle\ClientManifest\McpClientManifestGeneratorInterface;
use Ufo\McpBundle\ConfigService\McpGroupConfig;

/** Served at GET /manifest/claude.json — a bare "mcpServers" map, the shape Claude Code's own .mcp.json/plugin config expects (no .claude-plugin/ wrapper; that part, if shipped, stays a separate static asset). */
#[AutoconfigureTag(McpClientManifestGeneratorHolder::TAG, ['key' => 'claude'])]
final class ClaudeCodeManifestGenerator implements McpClientManifestGeneratorInterface
{
    public function generate(McpGroupConfig $group, string $endpointUrl, string $tokenHeaderName): array
    {
        return [
            'mcpServers' => [
                $group->name => [
                    'type' => 'http',
                    'url' => $endpointUrl,
                    'headers' => [$tokenHeaderName => '${UFO_MCP_TOKEN}'],
                ],
            ],
        ];
    }
}
