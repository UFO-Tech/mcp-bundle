[Українська](architecture-UA.md) · [Documentation](README.md)

# Architecture

## Principles

1. **A thin adapter over the JSON-RPC bundle.** Like `ufo-tech/rest-bundle`, this bundle adds a protocol, not a second
   server. Business logic stays in RPC services (`IRpcService`); a tool call is an ordinary RPC request executed by the
   core. Whatever the core does for `/rpc` — parameter conversion, DTOs, `#[RPC\Assertions]`, cache, locks, RPC events,
   error format — it does for MCP too.
2. **Reuse, never duplicate.** Service map, API groups, versions, translations, locale negotiation, token security,
   cache pools, route imports and request execution all come from the core. When the core lacks an extension point,
   the fix goes to the core (that is how `IApiRouteImport` in 10.4.1 and `RpcExecutor` in 10.4.2 appeared), not into a
   copy here.
3. **The route is the source of truth.** The API group comes from the route import (`api_group`), the API version from
   the `{ver}` route segment. Config only tunes the server of a group; it never decides which methods exist or which
   version is served.
4. **Exposure = API group membership.** Every method of the group becomes a tool. There is no allowlist and no
   MCP-specific registration: `#[RPC\APIGroups]` already says where a method is published.
5. **The official MCP SDK does the protocol.** Handshake, sessions, the stateless 2026-07-28 protocol, argument
   validation against `inputSchema`, batching, CORS and DNS-rebinding middleware are `mcp/sdk` (`^0.8.1`). The bundle
   builds the SDK server for each request and plugs RPC methods into it.
6. **Fail loudly.** A method whose name is not a valid MCP tool name breaks its group (HTTP 500 with an explanation in
   debug) rather than silently disappearing from the tool list.

## Components

| Component | Role |
| --- | --- |
| `Routing\McpRouteImport` | Registers `config/router.yaml` with the core loader (`IApiRouteImport`): every import gets route names `<group>_ufo_mcp_*` and `_mcp_name_prefix` |
| `Controller\McpController` | Three actions: the MCP endpoint, `server.json`, client manifests |
| `EventListener\McpRequestListener` | The gate before every `McpController` action: API group, availability (404), token (401/403) |
| `Server\McpHandler` | Locale negotiation, transport, SDK server, `Content-Language` / `Vary` |
| `Server\Transport\StreamableHttpTransportFactory` | SDK Streamable HTTP transport with CORS, DNS-rebinding protection and the body limit of the group |
| `Server\McpServerFactory` | SDK server for one request: identity, instructions, session store, one tool per RPC method of the group and version |
| `Server\Session\RpcCacheSessionStore` | Sessions of the session-based protocol in the core cache pool `rpc.cache.pool` |
| `Tool\ToolSchemaFactory` | MCP `Tool` from core `Service` metadata: name check, texts, `inputSchema`, `outputSchema`, annotations; cached in core `RpcMetadataCache` |
| `Tool\RpcToolHandler` / `Tool\RpcToolExecutor` | `tools/call` → core `RpcExecutor` → MCP `CallToolResult` |
| `Server\RequestPrepare\Holders\RpcFromMcp` | Request carrier: builds the `RpcRequest` from the tool, its arguments and version |
| `DocAdapters\Outputs\ServerJsonAdapter` | MCP Registry `server.json` |
| `ClientManifest\*` | Client connection configs (Claude Code, Cursor, Gemini CLI, OpenAI), a tagged locator |
| `ConfigService\McpConfig` / `McpGroupConfig` / `McpServerInfoConfig` | Typed view of `ufo_mcp` config; a group without an entry gets the bundle-wide settings |
| `Security\McpAccessPolicyInterface`, `Availability\McpAvailabilityCheckerInterface` | Extension points (see [Extending](extending.md)) |

## Request flow

```
HTTP request — route from config/router.yaml, imported once per API group
  → McpRequestListener (kernel.controller, McpController only)
      ApiGroupContext ← route api_group / _mcp_name_prefix
      404: group disabled (McpAvailabilityCheckerInterface) or {ver} not in the group's ServiceMap versions
      OPTIONS (CORS preflight) passes without a token
      core IRpcSecurity: isValidApiRequest() for the endpoint, isValidDocRequest() for server.json / manifests
      401 (no token) / 403 (invalid token) as {"error": "..."}
  → McpController::mcpAction → McpHandler::handle()
      core RpcLocaleNegotiator → request locale
      StreamableHttpTransportFactory → PSR-7 request + SDK transport (CORS, DNS rebinding, max_body_bytes)
      McpServerFactory::create($ver, $locale) → SDK Server (tools of ServiceMap::getServices($ver) of the group)
      SDK: initialize / ping / tools/list / tools/call / DELETE session ...
      Content-Language, Vary: Accept-Language
  → tools/call → RpcToolHandler → RpcToolExecutor
      RpcFromMcp(service, arguments, version) → core RpcExecutor::execute()
        (same pipeline as /rpc; an escaping exception goes through rpc.error, the response is always an RpcResponse)
      core RpcRequestHandler::responseToArray() → CallToolResult (text + structuredContent | isError)
  → kernel.terminate: core PostResponseQueue fires rpc.post_response for every executed call
```

## What is built per request, what is cached

- The SDK server and its tool list are created for every HTTP request (the server is stateless between requests;
  sessions live in the cache pool).
- Each `Tool` is cached in the core `RpcMetadataCache` (`cache.system`), keyed by group, version, method and locale. The
  cache is reset on every container rebuild and disabled in debug — the same rule the core uses for its own docs. A
  compiler pass is deliberately not used: DTO classes are not tracked by the container, and building schemas needs
  application services.
- Sessions (session-based protocol only) live in `rpc.cache.pool` under `ufo_mcp_<group>_<session id>` with
  `session_ttl` per item.
