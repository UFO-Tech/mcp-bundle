<?php

declare(strict_types=1);

namespace Ufo\McpBundle\ClientManifest\AI;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\McpBundle\ClientManifest\McpClientManifestGeneratorHolder;
use Ufo\McpBundle\ClientManifest\McpClientManifestGeneratorInterface;
use Ufo\McpBundle\ConfigService\McpGroupConfig;

/**
 * Served at GET /manifest/openai.json. OpenAI/ChatGPT has no local client config file
 * analogous to Claude Code's/Cursor's/Gemini CLI's — this returns the "mcp" tool
 * object the Responses API expects inside a request's "tools" array instead (verified
 * against https://developers.openai.com/api/docs/guides/tools-remote-mcp, 2026-10-16).
 * The API never substitutes header values itself: whatever string sits in "headers" is
 * sent to the MCP server verbatim, so the token below is a literal placeholder the
 * caller's own code must replace before sending the request — there is no env-var
 * syntax to rely on here, unlike Claude Code/Cursor. "require_approval" defaults to
 * the conservative "always" so this bundle never silently authorizes unattended tool
 * calls; callers can relax it themselves.
 */
#[AutoconfigureTag(McpClientManifestGeneratorHolder::TAG, ['key' => 'openai'])]
final class OpenAiManifestGenerator implements McpClientManifestGeneratorInterface
{
    public function generate(McpGroupConfig $group, string $endpointUrl, string $tokenHeaderName): array
    {
        return [
            'type' => 'mcp',
            'server_label' => $group->name,
            'server_url' => $endpointUrl,
            'headers' => [$tokenHeaderName => '<UFO_MCP_TOKEN>'],
            'require_approval' => 'always',
        ];
    }
}
