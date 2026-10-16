<?php

declare(strict_types=1);

namespace Ufo\McpBundle\ClientManifest\AI;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\McpBundle\ClientManifest\McpClientManifestGeneratorHolder;
use Ufo\McpBundle\ClientManifest\McpClientManifestGeneratorInterface;
use Ufo\McpBundle\ConfigService\McpGroupConfig;

/**
 * Served at GET /manifest/gemini.json — Gemini CLI's own "mcpServers" shape
 * (~/.gemini/settings.json or <project>/.gemini/settings.json), using "httpUrl" for
 * Streamable HTTP ("url" is reserved there for the legacy SSE transport).
 * Gemini CLI documents $VAR_NAME/${VAR_NAME} expansion only for the "env" block of a
 * stdio server, not for "headers" (verified against
 * https://geminicli.com/docs/tools/mcp-server, 2026-10-16) — so, unlike Claude
 * Code/Cursor, the token below is a literal placeholder the user must paste over by
 * hand, not an env-var reference the client will resolve for them.
 */
#[AutoconfigureTag(McpClientManifestGeneratorHolder::TAG, ['key' => 'gemini'])]
final class GeminiManifestGenerator implements McpClientManifestGeneratorInterface
{
    public function generate(McpGroupConfig $group, string $endpointUrl, string $tokenHeaderName): array
    {
        return [
            'mcpServers' => [
                $group->name => [
                    'httpUrl' => $endpointUrl,
                    'headers' => [$tokenHeaderName => '<UFO_MCP_TOKEN>'],
                ],
            ],
        ];
    }
}
