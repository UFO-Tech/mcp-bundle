<?php

declare(strict_types=1);

namespace Ufo\McpBundle\ClientManifest\AI;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\McpBundle\ClientManifest\McpClientManifestGeneratorHolder;
use Ufo\McpBundle\ClientManifest\McpClientManifestGeneratorInterface;
use Ufo\McpBundle\ConfigService\McpGroupConfig;

/** Served at GET /manifest/cursor.json — same "mcpServers" map as Claude Code, but Cursor's own "${env:VAR}" env-substitution syntax instead of "${VAR}". */
#[AutoconfigureTag(McpClientManifestGeneratorHolder::TAG, ['key' => 'cursor'])]
final class CursorManifestGenerator implements McpClientManifestGeneratorInterface
{
    public function generate(McpGroupConfig $group, string $endpointUrl, string $tokenHeaderName): array
    {
        return [
            'mcpServers' => [
                $group->name => [
                    'url' => $endpointUrl,
                    'headers' => [$tokenHeaderName => '${env:UFO_MCP_TOKEN}'],
                ],
            ],
        ];
    }
}
