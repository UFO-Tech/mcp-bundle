[Українська](README-UA.md)

# ufo-tech/mcp-bundle 1.x

Major version 1 is the first public version of the bundle: an MCP server (Model Context Protocol) on top of `ufo-tech/json-rpc-bundle`. Every RPC method of an API group becomes an MCP tool; the call runs through the JSON-RPC bundle unchanged.

## Requirements

| | 1.0.0 |
| --- | --- |
| PHP | >= 8.4 |
| Symfony | ^7.2 \| ^8.0 (through `ufo-tech/json-rpc-bundle`) |
| ufo-tech/json-rpc-bundle | ^10.4.3 |
| mcp/sdk | ^0.8.1 |
| Other | `symfony/psr-http-message-bridge` ^7.2 \| ^8.0, `symfony/yaml` ^7.2 \| ^8.0, `nyholm/psr7` ^1.8 |

## Versions

| Version | Highlights |
| --- | --- |
| [1.0](1.0/README.md) | RPC methods of an API group as MCP tools; one endpoint per route import (group) and API version; session-based and stateless 2026-07-28 MCP; core token security (401/403), 404 for unavailable endpoints; titles and descriptions from core translations (per group); `inputSchema` / `outputSchema` from core metadata; `#[McpTool]` hints; sessions in the core cache pool; `server.json` for the MCP Registry; client manifests for Claude Code, Cursor, Gemini CLI, OpenAI |
