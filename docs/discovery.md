[Українська](discovery-UA.md) · [Documentation](README.md)

# server.json and client manifests

Every endpoint also serves two kinds of documents. Both are protected by the doc token rule (`protected_doc`), answer
404 like the endpoint itself, and take the URL from the request: `<prefix>[/{ver}]`.

## server.json — MCP Registry

`GET <prefix>/server.json` (or `<prefix>/v2/server.json`) — the document the
[MCP Registry](https://registry.modelcontextprotocol.io) expects when the server is published there
(schema `2025-12-11`). Not something a client reads to connect.

```json
{
  "$schema": "https://static.modelcontextprotocol.io/schemas/2025-12-11/server.schema.json",
  "name": "io.github.your-org/your-app",
  "title": "Your App MCP Server",
  "description": "Exposes Your App RPC services as MCP tools.",
  "version": "v1",
  "websiteUrl": "https://api.example.com",
  "icons": [{"src": "https://example.com/icon.png", "mimeType": "image/png", "sizes": ["128x128"]}],
  "remotes": [{"type": "streamable-http", "url": "https://api.example.com/mcp"}]
}
```

- `name`, `title`, `description`, `icons` — `mcp_server_info` of the group (falling back field by field to the
  bundle-wide one); unset fields are left out.
- `version` — the `{ver}` of the request; `websiteUrl` — scheme, host and port of the request; `remotes[0].url` — the
  endpoint URL.

## Client manifests

`GET <prefix>/manifest/{aiName}.json` — a ready connection config. The token stays a placeholder: a manifest never
contains a real token.

| `aiName` | For | Shape |
| --- | --- | --- |
| `claude` | Claude Code (`.mcp.json`) | `{"mcpServers": {"<group>": {"type": "http", "url": "…", "headers": {"Ufo-RPC-Token": "${UFO_MCP_TOKEN}"}}}}` |
| `cursor` | Cursor (`mcp.json`) | `{"mcpServers": {"<group>": {"url": "…", "headers": {"Ufo-RPC-Token": "${env:UFO_MCP_TOKEN}"}}}}` |
| `gemini` | Gemini CLI (`settings.json`) | `{"mcpServers": {"<group>": {"httpUrl": "…", "headers": {"Ufo-RPC-Token": "<UFO_MCP_TOKEN>"}}}}` — Gemini does not expand variables in headers |
| `openai` | OpenAI Responses API | the `tools[]` item `{"type": "mcp", "server_label": "<group>", "server_url": "…", "headers": {…}, "require_approval": "always"}` |

An unknown `aiName` answers 404 `{"error": "Unknown MCP client manifest \"…\"."}`. Your own client: see
[Extending](extending.md#client-manifest-for-another-client).
