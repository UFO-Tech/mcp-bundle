<?php

declare(strict_types=1);

namespace Ufo\McpBundle\ConfigService;

use function array_map;

/**
 * Typed "mcp_server_info" config — the fields an MCP Registry server.json needs (see
 * Ufo\McpBundle\DocAdapters\Outputs\ServerJsonAdapter), not the MCP protocol's own
 * "initialize" response and not a per-client connection config (see
 * Ufo\McpBundle\ClientManifest). Built once for the bundle-wide defaults and once per
 * group for its override (ufo_mcp.groups.<name>.mcp_server_info), then merged via
 * overriding(). There is deliberately no "version" field here: wherever a version is
 * needed (Ufo\McpBundle\Services\McpServerFactory, ServerJsonAdapter), it is always the
 * request's actual resolved API version (the "{ver}" route segment), never a static
 * config value that could drift from what the server is really serving.
 */
final readonly class McpServerInfoConfig
{
    public function __construct(
        public ?string $name = null,
        public ?string $title = null,
        public ?string $description = null,
        public array $icons = [],
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            name: $config['name'] ?? null,
            title: $config['title'] ?? null,
            description: $config['description'] ?? null,
            icons: array_map(static fn(array $icon) => [
                'src' => $icon['src'],
                'mimeType' => $icon['mime_type'] ?? null,
                'sizes' => $icon['sizes'] ?? [],
                'theme' => $icon['theme'] ?? null,
            ], $config['icons'] ?? []),
        );
    }

    /** Fields left unset here ($this = a group's override) fall back to $base's value. */
    public function overriding(self $base): self
    {
        return new self(
            name: $this->name ?? $base->name,
            title: $this->title ?? $base->title,
            description: $this->description ?? $base->description,
            icons: $this->icons !== [] ? $this->icons : $base->icons,
        );
    }
}
