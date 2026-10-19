[Українська](README-UA.md)

# 1.0 — RPC methods as MCP tools

Tags: 1.0.0. First released: 2026-10-19.

## Highlights

- **Your JSON-RPC API becomes an MCP server.** Every method of an API group is an MCP tool; AI agents (Claude Code,
  Cursor, Gemini CLI, OpenAI) call it over Streamable HTTP.
- **Nothing to rewrite.** A tool call is an ordinary core RPC request: parameter conversion, DTOs, validation, cache,
  locks, RPC events and the error format are the ones of `/rpc`.
- **One endpoint per API group and version**, protected by the same RPC tokens.
- **Both MCP protocol eras** on one URL: session-based (up to 2025-11-25) and stateless 2026-07-28.
- **Texts for agents.** Titles and descriptions from the core translations, per language and per API group.

## What's new

### Endpoints from route imports

```yaml
# config/routes/ufo_mcp.yaml
ufo_mcp:
    resource: ../../vendor/ufo-tech/mcp-bundle/config/router.yaml
    prefix: /mcp
    defaults: { api_group: mcp }
```

Each import is one endpoint; `api_group` is the core API group it serves, `/mcp/v2` serves API version 2. Route names
get the group (`mcp_ufo_mcp_endpoint`, …), so the same `router.yaml` is imported once per group. The `ufo_mcp.groups`
config is optional: a group without an entry runs on the bundle-wide settings.
[Configuration](../../../docs/configuration.md)

### Tools

- Exposure = core `#[RPC\APIGroups]`; name = RPC method name (an invalid MCP name fails the group loudly).
- Title and description from `translations/rpc.<locale>.yaml` and `translations/rpc/<group>.<locale>.yaml` by
  `Accept-Language`; `Content-Language` and `Vary` in the response.
- `inputSchema` and `outputSchema` from the core method metadata, self-contained (`$defs`); arrays accept JSON objects.
- `#[McpTool(readOnlyHint: …, destructiveHint: …, idempotentHint: …, openWorldHint: …)]` for MCP tool annotations.
- `tools/call` → core `RpcExecutor`: `structuredContent: {"result": …}` plus the same JSON as text; errors as the core RPC
  error with `isError: true`; `rpc.post_response` after the response is sent, once per call (also in a batch).

[Tools](../../../docs/tools.md)

### Security and HTTP

- Core `IRpcSecurity`: API token for the endpoint, doc token for `server.json` and manifests; 401 without a token, 403
  with an invalid one, as JSON.
- 404 for a disabled group (`McpAvailabilityCheckerInterface`) or an API version the group does not have — before the token
  check.
- CORS and DNS-rebinding protection of the SDK transport, configured by `McpAccessPolicyInterface`; body limit
  `max_body_bytes`.
- Sessions of the session-based protocol in the core cache pool `ufo_json_rpc.cache.pool` (share it, e.g. Redis, for
  several hosts).

[HTTP, sessions and security](../../../docs/http-and-security.md)

### server.json and client manifests

`GET /mcp/server.json` for the MCP Registry; `GET /mcp/manifest/{claude|cursor|gemini|openai}.json` — ready connection
configs; own clients through a tagged `McpClientManifestGeneratorInterface`.
[server.json and client manifests](../../../docs/discovery.md)

### Tests

Unit, functional (the bundle in a test application) and end-to-end tests (the official `mcp/sdk` client over HTTP on
2025-06-18, 2025-11-25 and 2026-07-28); 100% line coverage of `src/` by unit and functional tests. Also checked with MCP
Inspector. [Tests](../../../docs/testing.md)

## Upgrading from pre-release builds

Only relevant for applications that used the bundle from `dev-main` before 1.0.0:

- Config: `session_directory` (bundle-wide and per group) is removed — sessions are in the core cache pool; remove the
  key, otherwise "Unrecognized option". `groups` may now be empty.
- `ufo-tech/json-rpc-bundle` ^10.4.3 is required (`RpcExecutor` from 10.4.2; enum argument conversion and a serialization
  state fix — several `tools/call` in one batch — from 10.4.3 with `ufo-tech/rpc-objects` 3.9.2).
- Route names now contain the group (`<group>_ufo_mcp_endpoint`); drop a manual `name_prefix` from YAML imports.
- Titles and descriptions come only from the core translations: `ToolTextResolverInterface` and its implementations
  (`Ufo\McpBundle\I18n\*`) are removed.
- Classes moved from `Ufo\McpBundle\Services\*` to `Server\*`, `Server\Transport\*`, `Tool\*`; group, availability and
  token checks moved from `McpController` to `EventListener\McpRequestListener`.

## Known issues

- **OAuth** is not implemented: clients that require OAuth cannot connect (the token goes in a header).
- **Coverage under Xdebug 3.4.2** ends in a segmentation fault on the tests that run the SDK server; use pcov.
