<?php

declare(strict_types=1);

namespace Ufo\McpBundle\ClientManifest;

use Ufo\McpBundle\ConfigService\McpGroupConfig;

/**
 * Produces a ready-to-use MCP client connection config for one AI tool (Claude Code,
 * Cursor, a custom one you register yourself — see README): "how do I connect to this
 * server", served at GET /manifest/{aiName}.json. This is not the MCP Registry's
 * publication manifest — see Ufo\McpBundle\DocAdapters\Outputs\ServerJsonAdapter for
 * that — and not the MCP protocol's own "initialize" response.
 * Tag your implementation
 * #[AutoconfigureTag(McpClientManifestGeneratorHolder::TAG, ['key' => '<aiName>'])]
 * and McpController::manifestAction() finds it by that key from the route's {aiName}.
 */
interface McpClientManifestGeneratorInterface
{
    /**
     * @return array JSON-serializable manifest content for this target.
     */
    public function generate(McpGroupConfig $group, string $endpointUrl, string $tokenHeaderName): array;
}
