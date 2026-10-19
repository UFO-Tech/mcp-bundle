# UFO MCP Bundle
![Ukraine](https://img.shields.io/badge/Glory-Ukraine-yellow?labelColor=blue)
![License](https://img.shields.io/badge/license-MIT-green?labelColor=7b8185)

[Українська](README-UA.md)

MCP server (Model Context Protocol) for [`ufo-tech/json-rpc-bundle`](https://github.com/UFO-Tech/json-rpc-bundle):
the methods of your JSON-RPC API become tools that AI agents — Claude Code, Cursor, Gemini CLI, OpenAI — call over
Streamable HTTP. Like `ufo-tech/rest-bundle`, it is a thin adapter: business logic stays in RPC services, and every tool
call runs through the JSON-RPC bundle unchanged.

Requires PHP 8.4+, Symfony 7.2+ / 8, `ufo-tech/json-rpc-bundle` ^10.4.3, the official
[`mcp/sdk`](https://github.com/modelcontextprotocol/php-sdk) ^0.8.1.

```bash
composer require ufo-tech/mcp-bundle
```

## Quick start

1. Register the bundle in `config/bundles.php`:

   ```php
   Ufo\McpBundle\UfoMcpBundle::class => ['all' => true],
   ```

2. Copy `install/packages/ufo_mcp.yaml` to `config/packages/` and `install/routes/ufo_mcp.yaml` to `config/routes/`.
   One route import is one MCP endpoint serving one API group:

   ```yaml
   # config/routes/ufo_mcp.yaml
   ufo_mcp:
       resource: ../../vendor/ufo-tech/mcp-bundle/config/router.yaml
       prefix: /mcp
       defaults: { api_group: mcp }
   ```

3. Put RPC methods into that group:

   ```php
   use Ufo\McpBundle\Attribute\McpTool;
   use Ufo\RpcObject\RPC;

   #[RPC\APIGroups(['mcp'], withDefault: false)]
   class CatalogProcedure implements IRpcService
   {
       /** Search products by name. */
       #[McpTool(readOnlyHint: true)]
       public function search(string $query, int $limit = 10): array { /* ... */ }
   }
   ```

4. Connect a client: `GET /mcp/manifest/claude.json` (or `cursor`, `gemini`, `openai`) returns a ready config; the
   token is the JSON-RPC bundle's RPC token (`Ufo-RPC-Token` header).

## Key features

- **Exposure by API group.** Every method of the group is a tool, named after the RPC method; several endpoints
  (`/mcp`, `/mcp/internal`, …) side by side, each with its own methods — [configuration](docs/configuration.md)
- **API versions.** `/mcp/v2` serves version 2 of the group — [configuration](docs/configuration.md#api-versions)
- **The core does the work.** A tool call is an RPC request: parameter conversion, DTOs, validation, cache, locks, RPC
  events and errors as on `/rpc` — [tools](docs/tools.md#toolscall)
- **Schemas and texts for agents.** `inputSchema` / `outputSchema` from RPC metadata; titles and descriptions from the
  core translations by `Accept-Language`, also per group; `#[McpTool]` hints — [tools](docs/tools.md)
- **Both MCP protocol eras.** Session-based (sessions in the core cache pool) and stateless 2026-07-28 on one URL —
  [HTTP](docs/http-and-security.md#protocol-versions)
- **Same security.** Core RPC tokens (401/403), 404 for a disabled group or a missing version, CORS and DNS-rebinding
  protection, body limit — [security](docs/http-and-security.md)
- **Discovery.** `server.json` for the MCP Registry and client manifests — [discovery](docs/discovery.md)
- **Extensible.** Availability checker, access policy, own client manifests — [extending](docs/extending.md)

Not supported: OAuth, MCP resources and prompts, server-initiated messages. See
[limitations](docs/README.md#limitations).

## Release notes

All versions with what was added and how to use it: [releases](releases/README.md). Latest: [1.0](releases/1.x/1.0/README.md).

## Documentation

Installation, configuration and how the bundle works: [MCP Bundle documentation](http://localhost/uk/docs/api/mcp-bundle).

## Development

PHP runs in Docker (`ufotech/php:8.4-fpm`):

```sh
make up-d              # start the container
make composer-install
make test              # unit, functional and end-to-end tests
```
