# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Symfony bundle (`ufo-tech/mcp-bundle`) that exposes existing `ufo-tech/json-rpc-bundle`
RPC services as MCP (Model Context Protocol) tools over Streamable HTTP. It is a thin
adapter, analogous to `ufo-tech/rest-bundle`: it does not implement its own business logic,
validation or persistence — every tool call is translated into a JSON-RPC request and run
through the RPC bundle's own request handler, parameter conversion, validation and event
pipeline. Requires PHP >= 8.4, uses the official `mcp/sdk` (^0.8.1).

## Development environment

There is no local PHP toolchain assumed on the host; PHP runs inside Docker
(`ufotech/php:8.4-fpm`, see `docker-compose.yaml`). Standard flow:

```sh
make up-d              # start the php_mcp container (copies .env.dist -> .env on first run)
make exec               # open a shell in the container
make composer CMD=install   # or: make composer-install
```

Run tests inside the container:

```sh
vendor/bin/phpunit                                  # full suite
vendor/bin/phpunit --filter testCallsUseRpcDefaults  # single test by name
vendor/bin/phpunit tests/Unit/McpServiceMapTest.php  # single file
```

`docker-compose.yaml` also bind-mounts sibling repos one level up
(`json-rpc-sdk-bundle`, `json-rpc-bundle`, `rpc-objects`, `rpc-exceptions`,
`dto-transformer`) at the same relative path inside the container, for local
cross-package development against unreleased versions of those packages.

## Architecture

### Request flow

```
HTTP request
  → McpEntryPointController (src/Controller)
      - checks mcp_options.enabled, sets ApiGroupContext to the configured api_group
      - validates the RPC API token via IRpcSecurity (skipped only for OPTIONS)
      - converts the Symfony Request to PSR-7 and builds a StreamableHttpTransport
        with CORS + DNS-rebinding-protection middleware from mcp_options
  → McpServerFactory::create() (src/Services)
      - builds an mcp/sdk Server, registering one Tool + ToolHandlerInterface pair
        per exposed RPC method (see "Tool discovery" below)
  → SDK dispatches tools/list, tools/call, initialize, ping, sessions, etc.
  → RpcToolHandler::execute() → RpcToolExecutor::execute()
      - wraps the MCP arguments into a Ufo\RpcObject\RpcRequest and an
        RpcFromMcp request carrier, then calls RpcRequestHandler::provideSingleRequest()
        — the same entry point the JSON-RPC bundle itself uses, so DTO/enum
        conversion, #[RPC\Assertions] validation, caching and
        RpcEvent::POST_RESPONSE listeners all still run
      - maps the RPC response into a CallToolResult: errors become
        CallToolResult::error(...), successes carry both a JSON text block and
        structuredContent: {"result": ...}
```

### Tool discovery (`McpServiceMap` + `ToolSchemaFactory`)

- `McpServiceMap::getServices()` reads `ServiceMap::getServices($api_version)` for the
  configured `api_group`/`api_version` and decides, per RPC service, whether it becomes
  an MCP tool:
  - if `mcp_options.methods` is **non-empty**, it is authoritative: only those RPC
    names are exposed, whether or not they carry `#[McpTool]`, and an unknown
    configured method throws `LogicException` at boot.
  - if `methods` is empty, only methods carrying `#[McpTool]` (`src/Attribute/McpTool.php`)
    are exposed.
  - the exposed name is `#[McpTool(name: ...)]` if set, else the RPC method name; it
    must match `/^[a-zA-Z0-9_.-]{1,128}$/D` and must be unique — both are enforced here,
    not by the SDK.
- `ToolSchemaFactory::create()` turns RPC parameter metadata into a JSON-Schema
  `inputSchema`, reusing the RPC bundle's own `JsonSchemaDtoFormatTrait`/DTO schema
  generation, then rewrites any `#/components/schemas/...` ref produced for DTOs/enums
  into a local `#/$defs/...` ref and attaches the collected schemas under
  `inputSchema.$defs`. This is required because MCP clients cannot resolve OpenRPC
  component refs — each tool must be self-contained.
- `McpTool` attribute fields (`name`, `description`, `title`, `readOnlyHint`,
  `destructiveHint`, `idempotentHint`, `openWorldHint`) map directly onto the MCP
  `Tool`/`ToolAnnotations` schema; when a method has no attribute at all (only reachable
  via the `methods` allowlist), `annotations` is `null` and the tool description falls
  back to the RPC method's own description.

### Wiring

- `DependencyInjection/Configuration.php` defines the `ufo_mcp` config tree (`enabled`,
  `path`, `api_group`, `api_version`, `server_name`/`server_version`/`instructions`,
  `methods`, `allowed_hosts`, `allowed_origins`, `max_body_bytes`, `session_directory`,
  `session_ttl`). `UfoMcpExtension` stores the processed config as a single array
  parameter (`ufo_mcp.options`) and loads `config/services.yaml`.
- `config/services.yaml` autowires all services and binds `$mcpOptions` to
  `%ufo_mcp.options%` everywhere — that's how every service in `src/Services` and the
  controller receive the config array instead of named parameters.
- `McpRouteLoader` (tagged `routing.loader`, loaded via `install/routes/ufo_mcp.yaml`
  with `type: ufo_mcp`) registers the single `/mcp`-style route (GET/POST/DELETE/OPTIONS)
  only when `enabled` is true, pointing at `McpController`.
- `install/packages/ufo_mcp.yaml` and `install/routes/ufo_mcp.yaml` are the files a
  consuming app copies into its own `config/packages` and `config/routes` (there is no
  Flex recipe yet). `install/claude-code-plugin` and `install/clients/cursor.json` are
  ready-made MCP client configs for agent tooling, not part of the bundle's runtime.

### Sessions and protocol

Legacy MCP sessions are persisted via `Mcp\Server\Session\FileSessionStore` under
`session_directory` (shared across HTTP workers/hosts); this is skipped entirely for
stateless clients using the 2026-07-28 protocol (`Mcp-Protocol-Version: 2026-07-28` plus
`Mcp-Method`/`Mcp-Name` headers instead of a session handshake — see
`testModernStateless*` in the functional tests). Tool execution is synchronous only; RPC
async/callback features are not exposed through MCP.

## Testing architecture

`tests/Functional/TestKernel` boots a minimal real Symfony kernel
(`FrameworkBundle` + `SecurityBundle` + `UfoJsonRpcBundle` + `UfoMcpBundle`) with RPC
security enabled and two token-scoped groups (`general` via `test-token`, `partners` via
`partner-token`). Fixture RPC services (`CatalogProcedure`, `CatalogV2Procedure`,
`PartnerProcedure` in `tests/Functional`) are real `IRpcService` implementations
annotated with `#[RPC\Info]`/`#[McpTool]`, registered as public services, and exercised
through genuine HTTP requests built with `Symfony\Component\HttpFoundation\Request` and
dispatched via `$kernel->handle()` — there is no mocking of the MCP or RPC pipeline.
When adding functional coverage for a new behavior, add or extend a fixture procedure
rather than mocking `Service`/`ServiceMap`. `tests/Unit/McpServiceMapTest.php` mocks
`ServiceMap` directly to test exposure/alias/validation logic in isolation; keep that
split (unit tests mock the RPC service map, functional tests run the real kernel).
