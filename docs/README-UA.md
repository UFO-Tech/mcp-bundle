[English](README.md)

# Документація UFO MCP Bundle

`ufo-tech/mcp-bundle` публікує методи `ufo-tech/json-rpc-bundle` як tools MCP-сервера (Model Context
Protocol, Streamable HTTP), щоб AI-агенти (Claude, Cursor, Gemini CLI, OpenAI, …) могли викликати ваш API.

| Сторінка | Що всередині |
| --- | --- |
| [Архітектура](architecture-UA.md) | Принципи, компоненти, шлях запиту, що будується на кожен запит і що кешується |
| [Встановлення та конфігурація](configuration-UA.md) | Встановлення, маршрути, довідник конфігурації, групи та версії API, сесії |
| [Tools](tools-UA.md) | Назви, заголовки й описи, `inputSchema`, `outputSchema`, анотації, результати `tools/call`, помилки, події |
| [HTTP, сесії та безпека](http-and-security-UA.md) | Версії протоколу, порядок перевірок (404/401/403), токен, CORS, DNS rebinding, ліміт тіла запиту |
| [server.json і маніфести клієнтів](discovery-UA.md) | Документ для MCP Registry та готові конфігурації клієнтів |
| [Розширення](extending-UA.md) | Доступність, політика доступу, власні маніфести клієнтів, тексти для агентів, підказки для tools |
| [Тести](testing-UA.md) | Набори тестів, фікстури, покриття, перевірка через MCP Inspector |

Нотатки до релізів: [releases](../releases/README-UA.md).

## За одну хвилину

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

Тепер `POST /mcp` — це MCP-сервер із tool `Catalog.search`, захищений RPC-токеном; `GET /mcp/manifest/claude.json`
віддає конфігурацію Claude Code для підключення до нього.

## Обмеження

- Без OAuth: доступ — це RPC-токен core в заголовку (див. [HTTP і безпека](http-and-security-UA.md#заголовок-із-токеном)).
- Лише tools: без MCP resources і prompts; SDK типово оголошує capabilities `logging` і `completions`, але бандл
  їх не використовує.
- Без повідомлень з ініціативи сервера (`GET` відповідає 405), без async/callback-викликів через MCP.
