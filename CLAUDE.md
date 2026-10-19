# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Symfony bundle (`ufo-tech/mcp-bundle`) that exposes existing `ufo-tech/json-rpc-bundle`
RPC services as MCP (Model Context Protocol) tools over Streamable HTTP. It is a thin
adapter, analogous to `ufo-tech/rest-bundle`: it must **extend** the JSON-RPC bundle
("core") and reuse what it already provides — service map, groups, translations, locale
negotiation, security, caches, route loading — never duplicate it. When core lacks an
extension point, the right fix is usually in core, not a copy here.

Requires PHP >= 8.4, `ufo-tech/json-rpc-bundle` ^10.4.3, official `mcp/sdk` ^0.8.1.

Discussion notes and decisions live in `.features/` (gitignored); read its `README.md` first.

## Development environment

No local PHP toolchain is assumed; PHP runs in Docker (`ufotech/php:8.4-fpm`, see
`docker-compose.yaml`):

```sh
make up-d              # start the php_mcp container (copies .env.dist -> .env on first run)
make exec              # shell in the container
make composer-install
```

`docker-compose.yaml` bind-mounts sibling repos (`json-rpc-bundle`, `rpc-objects`,
`rpc-exceptions`, `dto-transformer`, ...) for cross-package development.

## Tests

```sh
docker exec -w /app php_mcp vendor/bin/phpunit                      # all suites (make test needs a TTY)
docker exec -w /app php_mcp vendor/bin/phpunit --testsuite Unit      # Unit | Functional | E2E
```

- `tests/Unit` — plain PHPUnit with mocks.
- `tests/Functional` — `McpKernelTestCase` sends HTTP requests through `Fixtures\TestKernel`
  (`handle()` + `terminate()`); `TestKernel::$mcpConfig` / `$serviceOverrides` change the
  config or replace a service per test. Fixture app: procedures in groups `mcp`, `internal`,
  `off`, `bad`, a v2 procedure, translations in `tests/Fixtures/translations`, routes in
  `tests/Fixtures/config/routes.yaml`.
- `tests/E2E` — the official `mcp/sdk` client against `php -S` serving
  `tests/Fixtures/public/index.php`, in three protocol versions.
- Coverage: Xdebug 3.4.2 segfaults on the SDK-server tests; use pcov (see `docs/testing.md`).

User docs live in `docs/` (EN + `-UA`), release notes in `releases/` (same layout as core).

## Architecture

### Request flow

```
HTTP request (route from config/router.yaml, imported per API group)
  → EventListener\McpRequestListener (kernel.controller, McpController only)
      - sets ApiGroupContext from the route's api_group / _mcp_name_prefix
      - 404 if the group is disabled (McpAvailabilityCheckerInterface) or lacks the
        {ver} API version; an ufo_mcp.groups entry is optional (route defines the group)
      - OPTIONS passes without a token (CORS preflight)
      - token via core IRpcSecurity: isValidApiRequest() for mcpAction,
        isValidDocRequest() for server.json/manifests; 401/403 JSON on failure
  → Controller\McpController::mcpAction → Server\McpHandler::handle()
      - negotiates the locale with core RpcLocaleNegotiator, sets it on the request,
        adds Content-Language / Vary to the response
      - Server\Transport\StreamableHttpTransportFactory: PSR-7 request + SDK
        StreamableHttpTransport with CORS / DNS-rebinding middleware
        (McpAccessPolicyInterface)
  → Server\McpServerFactory::create($version, $locale)
      - SDK Server with identity from mcp_server_info, legacy sessions in
        Server\Session\RpcCacheSessionStore (PSR-6, core rpc.cache.pool)
      - one Tool + Tool\RpcToolHandler per service of ServiceMap::getServices($version)
        for the current group
  → SDK dispatches initialize / tools/list / tools/call / ...
  → Tool\RpcToolExecutor::execute()
      - Server\RequestPrepare\Holders\RpcFromMcp builds the RpcRequest from the tool's
        service, arguments and version; the carrier goes to core RpcExecutor::execute()
        (10.4.2) — DTO/enum conversion, assertions, cache and RPC events all run; an
        escaping exception goes through rpc.error, so it always gets an RpcResponse
      - error → CallToolResult::error with the core RpcError JSON (core responseToArray());
        success → JSON text + structuredContent {"result": ...}
      - RpcEvent::POST_RESPONSE is deferred by core PostResponseQueue and fired on
        kernel.terminate (none for cached or error responses)
```

### Tools (`Tool\ToolSchemaFactory`)

- Exposure = API group membership (core `#[APIGroups]`); there is no allowlist and
  `#[McpTool]` only carries ToolAnnotations hints.
- Built at request time and cached in core `RpcMetadataCache` (`cache.system`, keyed by
  group:version:method:locale, reset on container rebuild, off in debug) — the same
  pattern core uses for its own docs/DTO formats. Deliberately **not** a compiler pass:
  DTO classes aren't tracked by the container, and building schemas needs app services.
- Name is validated against `/^[a-zA-Z0-9_.-]{1,128}$/D` here (only for real tools);
  an invalid one throws — decided: fail loudly, don't skip.
- Title/description: `Service::getTitle($locale)` / `getDescription($locale)` — core
  translations, with group overrides in `translations/rpc/<group>.<locale>.yaml`.
- `inputSchema` / `outputSchema` reuse core `JsonSchemaDtoFormatTrait`; OpenRPC
  `#/components/schemas/...` refs are rewritten to local `#/$defs/...`. In `outputSchema`
  every `"type":"array"` becomes `["array","object"]` (PHP assoc arrays are JSON objects).
  No `outputSchema` when core describes the return as bare "any object" (no return type /
  `object`): it would reject a scalar result.

### Wiring

- `DependencyInjection/Configuration.php` — `ufo_mcp` tree: `enabled`, `instructions`,
  `max_body_bytes`, `session_ttl`, `mcp_server_info`, `groups` (optional per-group overrides of
  all of those). `ConfigService\McpConfig` / `McpGroupConfig` / `McpServerInfoConfig`
  are the typed view; there is no version config — it's always the `{ver}` route segment.
- `config/services.yaml` autowires `src/`; `McpConfig` gets `%ufo_mcp.options%`.
- `Routing\McpRouteImport` implements core `IApiRouteImport` (10.4.1), so each YAML
  import of `config/router.yaml` gets `<group>_<name>` route names and `_mcp_name_prefix`.
- `ClientManifest\*` — `GET .../manifest/{aiName}.json` client configs (claude, cursor,
  gemini, openai), a tagged locator keyed by name.
- `DocAdapters\Outputs\ServerJsonAdapter` — `GET .../server.json` for the MCP Registry.
- `install/packages/ufo_mcp.yaml` and `install/routes/ufo_mcp.yaml` are what a consuming
  app copies into its config (no Flex recipe yet).
