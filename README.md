# UFO MCP Bundle

Expose existing `ufo-tech/json-rpc-bundle` services as MCP tools. Like
`ufo-tech/rest-bundle`, this is a separate Symfony adapter: business logic stays
in RPC services, and calls pass through the RPC request handler, validation,
DTO/parameter converters, cache, and events.

Requires PHP >= 8.4 and JSON-RPC Bundle ^10.2. Uses the official
[`mcp/sdk`](https://github.com/modelcontextprotocol/php-sdk) ^0.8.1 and supports
Streamable HTTP, including the session-based handshake and the stateless
2026-07-28 protocol. [Українською](README-UA.md).

## Installation

Until the package is published, add a Composer path repository pointing at this
checkout and require `ufo-tech/mcp-bundle:@dev`. Once published:

```sh
composer require ufo-tech/mcp-bundle
```

Register the bundle in the application's `config/bundles.php`, alongside the
existing JSON-RPC bundle:

```php
Ufo\McpBundle\UfoMcpBundle::class => ['all' => true],
```

Copy `install/packages/ufo_mcp.yaml` and `install/routes/ufo_mcp.yaml` into the
application's corresponding configuration directories. There is no automatic
Flex recipe yet. Set `allowed_hosts` to your deployment's hostname.

## Exposing tools

By default, only annotated methods are exposed:

```php
use Ufo\McpBundle\Attribute\McpTool;

#[McpTool(description: 'Search products by name.', readOnlyHint: true)]
public function search(string $query, int $limit = 10): array
{
    // Existing RPC implementation.
}
```

Alternatively, select existing methods without adding attributes:

```yaml
ufo_mcp:
    path: /mcp
    api_group: general
    api_version: v1
    methods: [catalog.search, catalog.get]
    allowed_hosts: [api.example.com]
```

A non-empty `methods` list is authoritative: annotated methods outside it are
hidden too. Names in this list are RPC names, even if a tool has an alias.
`#[McpTool(name: 'search_products')]` overrides its MCP name; descriptions,
titles and MCP annotations are also optional. Aliases must be unique and use
1–128 letters, digits, underscores, hyphens or dots. Unknown configured methods
and duplicate names fail explicitly.

Input schemas come from RPC metadata, including required/default parameters,
DTOs and enums. Each tool carries local `$defs` for schema references. Existing
RPC conversion attributes still apply (for example `#[RPC\Param(...)]` for an
enum parameter); MCP does not change PHP parameter conversion rules.

## Authentication and HTTP

The endpoint checks `IRpcSecurity::isValidApiRequest()` on every request except
CORS preflight, including discovery and session requests. It uses the existing
RPC token header, `protected_api`, token validators and group-specific providers.
Enable `ufo_json_rpc.security.protected_api` to require authentication. This
adapter supplies API-token authentication; it does not implement an OAuth
authorization server or OAuth discovery for clients that require it.

`allowed_hosts` controls SDK Host/Origin validation and defaults to loopback
hosts. `allowed_origins` controls browser CORS responses and defaults to none.
For browser clients, configure both the permitted Origin hostname and its full
origin URL. CORS preflight does not expose tools or execute RPC calls. If you
customize `token_name`, configure that header in the client. The endpoint's
preflight response includes the configured RPC token header.

The SDK handles `initialize`, `notifications/initialized`, `ping`, `tools/list`,
`tools/call`, protocol negotiation, pagination, sessions and protocol errors.
Tool execution errors use `isError: true`. Successful results include text
containing the JSON result plus `structuredContent: {"result": ...}`; this
wrapper also supports scalar, null and list results in older MCP clients.
Tools run synchronously; RPC async controls and callbacks are not exposed.
Resources, prompts and server-initiated interactions are outside this adapter's
scope. Annotations describe tools; authorization remains application-side.

Legacy sessions use `session_directory` (default `%kernel.cache_dir%/ufo_mcp_sessions`)
and `session_ttl` (default 3600 seconds). Multiple HTTP workers must share that
directory; multiple hosts need a shared volume or a customized server factory.
Stateless clients do not require session storage. `max_body_bytes` defaults to
4 MiB. Set `enabled: false` to remove the MCP route.

## Agent clients and plugin

For Claude Code, copy `install/claude-code-plugin/.mcp.json` into the client
project, or load the complete plugin:

```sh
export UFO_MCP_URL=https://api.example.com/mcp
export UFO_MCP_TOKEN=your-api-token
claude --plugin-dir ./vendor/ufo-tech/mcp-bundle/install/claude-code-plugin
```

The plugin directory includes its manifest and can be copied and customized for
your API. For Cursor, use `install/clients/cursor.json` as the project MCP config
and provide the same environment variables. Adjust the header if your RPC
application uses a custom token name. See the official
[Claude Code MCP docs](https://code.claude.com/docs/en/mcp),
[plugin reference](https://code.claude.com/docs/en/plugins-reference), and
[Cursor MCP docs](https://cursor.com/docs/mcp).

## Development

```sh
composer install
vendor/bin/phpunit
```

Functional tests boot Symfony with the JSON-RPC bundle and exercise real MCP
HTTP requests through the official SDK and the RPC execution pipeline.
