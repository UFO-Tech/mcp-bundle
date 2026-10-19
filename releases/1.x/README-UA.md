[English](README.md)

# ufo-tech/mcp-bundle 1.x

Мажорна версія 1 — перша публічна версія бандла: MCP-сервер (Model Context Protocol) поверх `ufo-tech/json-rpc-bundle`. Кожен RPC-метод групи API стає MCP tool; виклик проходить через JSON-RPC bundle без змін.

## Вимоги

| | 1.0.0 |
| --- | --- |
| PHP | >= 8.4 |
| Symfony | ^7.2 \| ^8.0 (через `ufo-tech/json-rpc-bundle`) |
| ufo-tech/json-rpc-bundle | ^10.4.3 |
| mcp/sdk | ^0.8.1 |
| Інше | `symfony/psr-http-message-bridge` ^7.2 \| ^8.0, `symfony/yaml` ^7.2 \| ^8.0, `nyholm/psr7` ^1.8 |

## Версії

| Версія | Головне |
| --- | --- |
| [1.0](1.0/README-UA.md) | RPC-методи групи API як MCP tools; ендпоінт на кожен імпорт маршрутів (групу) і версію API; MCP із сесіями і stateless 2026-07-28; безпека токенами core (401/403), 404 для недоступних ендпоінтів; назви й описи з перекладів core (зокрема для групи); `inputSchema` / `outputSchema` з метаданих core; підказки `#[McpTool]`; сесії в кеш-пулі core; `server.json` для MCP Registry; маніфести клієнтів для Claude Code, Cursor, Gemini CLI, OpenAI |
