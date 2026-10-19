[Українська](README-UA.md)

# UFO MCP Bundle documentation

`ufo-tech/mcp-bundle` exposes the methods of `ufo-tech/json-rpc-bundle` as tools of an MCP server (Model Context
Protocol, Streamable HTTP), so AI agents (Claude, Cursor, Gemini CLI, OpenAI, …) can call your API.

| Page | What's inside |
| --- | --- |
| [Architecture](architecture.md) | Principles, components, request flow, what is built per request and what is cached |
| [Installation and configuration](configuration.md) | Install, routes, config reference, API groups and versions, sessions |
| [Tools](tools.md) | Names, titles and descriptions, `inputSchema`, `outputSchema`, annotations, `tools/call` results, errors, events |
| [HTTP, sessions and security](http-and-security.md) | Protocol versions, order of checks (404/401/403), token, CORS, DNS rebinding, body limit |
| [server.json and client manifests](discovery.md) | MCP Registry document and ready client configs |
| [Extending](extending.md) | Availability, access policy, own client manifests, texts for agents, tool hints |
| [Tests](testing.md) | Test suites, fixtures, coverage, checking with MCP Inspector |

Release notes: [releases](../releases/README.md).

## In one minute

```php
#[RPC\APIGroups(['mcp'], withDefault: false)]
class CatalogProcedure implements IRpcService
{
    /** Search products by name. */
    #[McpTool(readOnlyHint: true)]
    public function search(string $query, int $limit = 10): array { /* ... */ }
}
```

```yaml
# config/routes/ufo_mcp.yaml
ufo_mcp:
    resource: ../../vendor/ufo-tech/mcp-bundle/config/router.yaml
    prefix: /mcp
    defaults: { api_group: mcp }
```

`POST /mcp` is now an MCP server with the tool `Catalog.search`, protected by the RPC token; `GET /mcp/manifest/claude.json`
gives the Claude Code config to connect to it.

## Limitations

- No OAuth: access is the core RPC token in a header (see [HTTP and security](http-and-security.md#token-header)).
- Tools only: no MCP resources or prompts; the SDK announces the `logging` and `completions` capabilities by default, they
  are not used by the bundle.
- No server-initiated messages (`GET` answers 405), no async/callback calls through MCP.
