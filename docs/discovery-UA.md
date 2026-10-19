[English](discovery.md) · [Документація](README-UA.md)

# server.json і маніфести клієнтів

Кожен ендпоінт також віддає два види документів. Обидва захищені правилом токена для документації (`protected_doc`), відповідають
404 так само, як сам ендпоінт, і беруть URL із запиту: `<prefix>[/{ver}]`.

## server.json — MCP Registry

`GET <prefix>/server.json` (або `<prefix>/v2/server.json`) — документ, який очікує
[MCP Registry](https://registry.modelcontextprotocol.io), коли сервер у ньому публікують
(схема `2025-12-11`). Клієнт не читає його для підключення.

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

- `name`, `title`, `description`, `icons` — `mcp_server_info` групи (з fallback поле за полем на загальне
  налаштування бандла); незадані поля не потрапляють у документ.
- `version` — `{ver}` запиту; `websiteUrl` — схема, хост і порт запиту; `remotes[0].url` — URL
  ендпоінта.

## Маніфести клієнтів

`GET <prefix>/manifest/{aiName}.json` — готова конфігурація підключення. Токен лишається плейсхолдером: маніфест ніколи
не містить справжнього токена.

| `aiName` | Для чого | Формат |
| --- | --- | --- |
| `claude` | Claude Code (`.mcp.json`) | `{"mcpServers": {"<group>": {"type": "http", "url": "…", "headers": {"Ufo-RPC-Token": "${UFO_MCP_TOKEN}"}}}}` |
| `cursor` | Cursor (`mcp.json`) | `{"mcpServers": {"<group>": {"url": "…", "headers": {"Ufo-RPC-Token": "${env:UFO_MCP_TOKEN}"}}}}` |
| `gemini` | Gemini CLI (`settings.json`) | `{"mcpServers": {"<group>": {"httpUrl": "…", "headers": {"Ufo-RPC-Token": "<UFO_MCP_TOKEN>"}}}}` — Gemini не підставляє змінні в заголовках |
| `openai` | OpenAI Responses API | елемент `tools[]` `{"type": "mcp", "server_label": "<group>", "server_url": "…", "headers": {…}, "require_approval": "always"}` |

Невідомий `aiName` відповідає 404 `{"error": "Unknown MCP client manifest \"…\"."}`. Власний клієнт: див.
[Розширення](extending-UA.md#маніфест-для-іншого-клієнта).
