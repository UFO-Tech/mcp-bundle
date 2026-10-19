[Українська](http-and-security-UA.md) · [Documentation](README.md)

# HTTP, sessions and security

## Protocol versions

The transport is the `mcp/sdk` Streamable HTTP transport. It serves both protocol eras on the same URL:

- **Session-based** (2024-11-05 … 2025-11-25): `initialize` → `Mcp-Session-Id` → requests with that header; `DELETE`
  ends the session. Sessions are stored in the core cache pool (see [configuration](configuration.md#sessions-and-a-shared-cache)).
  A request without a session answers 400, an unknown or expired session 404 (the client re-initializes).
- **Stateless 2026-07-28**: no handshake and no session. Every request carries
  `params._meta["io.modelcontextprotocol/protocolVersion"]` and the `MCP-Protocol-Version`, `Mcp-Method` (and for
  `tools/call` `Mcp-Name`) headers; the result carries `_meta["io.modelcontextprotocol/serverInfo"]`.

Tested end to end with the official SDK client over HTTP on 2025-06-18, 2025-11-25 and 2026-07-28, and with MCP Inspector
(TypeScript SDK).

`GET` (a standalone server-to-client SSE stream) answers 405: the server sends nothing on its own initiative.
JSON-RPC batches are accepted.

## Order of checks

`McpRequestListener` runs before every action of `McpController`:

1. **404** with an empty body — the group is disabled (`McpAvailabilityCheckerInterface`, by default `enabled`) or the
   group has no methods in the requested `{ver}`. This comes before the token check, so an unavailable endpoint looks the
   same with and without a token.
2. **OPTIONS** (CORS preflight) passes without a token; the transport answers it.
3. **Token** — the core `IRpcSecurity`:
   - the MCP endpoint: `isValidApiRequest()` (`protected_api`, token validators, per-group `security.providers`);
   - `server.json` and manifests: `isValidDocRequest()` — protected only when `protected_doc` is on;
   - no token → **401** `{"error": "Unauthorized. Token not found"}`; invalid → **403** `{"error": "Forbidden. Invalid token"}`.

The action is recognized by the controller method, not the route name, so a `name_prefix` on the import cannot bypass
the check.

## Token header

The core token header (`Ufo-RPC-Token` by default, `ufo_json_rpc.security.token_name`). The CORS
`Access-Control-Allow-Headers` includes it together with the MCP headers. Clients that support custom headers (Claude
Code, Cursor, Gemini CLI, the OpenAI Responses API) send it — the [client manifests](discovery.md) put it in place.

**OAuth is not implemented.** There is no `/.well-known/oauth-protected-resource` and no `WWW-Authenticate` on 401. MCP
makes authorization optional, but clients that only support OAuth (for example MCP Inspector without a header, which then
starts its OAuth flow) cannot connect.

## CORS and DNS rebinding

`McpAccessPolicyInterface` (default `DefaultMcpAccessPolicy`) controls two layers of the SDK transport:

- `getAllowedOrigins()` — CORS. Default `['*']`; `[]` sends no CORS headers.
- `getAllowedHosts()` — DNS-rebinding protection. Default `[]` = off. With a list, a request whose `Host` is not listed
  answers 403. **When the request has an `Origin` header, the SDK checks the Origin's host against the same list**, so a
  browser client's host must be listed too.

```php
final class AppMcpAccessPolicy implements McpAccessPolicyInterface
{
    public function getAllowedHosts(): array { return ['api.example.com', 'app.example.com']; }
    public function getAllowedOrigins(): array { return ['https://app.example.com']; }
}
```

```yaml
# config/services.yaml
Ufo\McpBundle\Security\McpAccessPolicyInterface: '@App\Mcp\AppMcpAccessPolicy'
```

## Body limit

`max_body_bytes` (per group) — a larger request body answers 413 with a JSON-RPC error.
