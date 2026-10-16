<?php

declare(strict_types=1);

namespace Ufo\McpBundle\ClientManifest;

use Ufo\McpBundle\ConfigService\McpGroupConfig;

/**
 * Produces a ready-to-use MCP client connection config for one AI tool (Claude Code,
 * Cursor, a custom one you register yourself — see docs/): "how do I connect to this
 * server", served at GET /manifest/{ai_name}.json. This is not the MCP Registry's
 * publication manifest — see Ufo\McpBundle\DocAdapters\Outputs\ServerJsonAdapter for
 * that — and not the MCP protocol's own "initialize" response.
 * Tag your implementation
 * #[AutoconfigureTag(McpClientManifestGeneratorHolder::TAG, ['key' => '<ai_name>'])]
 * and McpController::manifestAction() finds it by that key from the route's {ai_name}.
 */
interface McpClientManifestGeneratorInterface
{
    /**
     * @return array JSON-serializable manifest content for this target.
     */
    public function generate(McpGroupConfig $group, string $endpointUrl, string $tokenHeaderName): array;
}
