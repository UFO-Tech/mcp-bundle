[English](README.md)

# 1.0 — RPC-методи як MCP tools

Теги: 1.0.0. Перший реліз: 2026-10-19.

## Головне

- **Ваш JSON-RPC API стає MCP-сервером.** Кожен метод групи API — це MCP tool; AI-агенти (Claude Code, Cursor, Gemini
  CLI, OpenAI) викликають його через Streamable HTTP.
- **Нічого не треба переписувати.** Виклик tool — звичайний RPC-запит core: перетворення параметрів, DTO, валідація,
  кеш, блокування, RPC-події і формат помилок ті самі, що в `/rpc`.
- **Ендпоінт на кожну групу API і версію**, захищений тими самими RPC-токенами.
- **Обидві епохи протоколу MCP** на одному URL: із сесіями (до 2025-11-25) і stateless 2026-07-28.
- **Тексти для агентів.** Назви й описи з перекладів core — для кожної мови і кожної групи API.

## Що нового

### Ендпоінти з імпортів маршрутів

```yaml
# config/routes/ufo_mcp.yaml
ufo_mcp:
    resource: ../../vendor/ufo-tech/mcp-bundle/config/router.yaml
    prefix: /mcp
    defaults: { api_group: mcp }
```

Кожен імпорт — один ендпоінт; `api_group` — група API core, яку він обслуговує, `/mcp/v2` обслуговує версію API 2. Імена
маршрутів отримують групу (`mcp_ufo_mcp_endpoint`, …), тож той самий `router.yaml` імпортується окремо для кожної групи.
Конфіг `ufo_mcp.groups` необов'язковий: група без запису працює на загальних налаштуваннях.
[Налаштування](../../../docs/configuration-UA.md)

### Tools

- Що стає tool — визначає `#[RPC\APIGroups]` core; назва = назва RPC-методу (невалідна для MCP назва гучно ламає групу).
- Назва й опис з `translations/rpc.<locale>.yaml` і `translations/rpc/<group>.<locale>.yaml` за `Accept-Language`;
  у відповіді `Content-Language` і `Vary`.
- `inputSchema` і `outputSchema` з метаданих методів core, самодостатні (`$defs`); масиви приймають JSON-об'єкти.
- `#[McpTool(readOnlyHint: …, destructiveHint: …, idempotentHint: …, openWorldHint: …)]` — анотації MCP tool.
- `tools/call` → `RpcExecutor` core: `structuredContent: {"result": …}` плюс той самий JSON текстом; помилки — RPC-помилка
  core з `isError: true`; `rpc.post_response` після відправки відповіді, один раз на виклик (і в batch).

[Tools](../../../docs/tools-UA.md)

### Безпека і HTTP

- `IRpcSecurity` core: API-токен для ендпоінта, doc-токен для `server.json` і маніфестів; 401 без токена, 403 з невалідним,
  у JSON.
- 404 для вимкненої групи (`McpAvailabilityCheckerInterface`) або версії API, якої в групі немає — ще до перевірки токена.
- CORS і захист від DNS rebinding транспорту SDK налаштовує `McpAccessPolicyInterface`; ліміт тіла `max_body_bytes`.
- Сесії протоколу із сесіями — у кеш-пулі core `ufo_json_rpc.cache.pool` (для кількох хостів — спільний, наприклад Redis).

[HTTP, сесії і безпека](../../../docs/http-and-security-UA.md)

### server.json і маніфести клієнтів

`GET /mcp/server.json` для MCP Registry; `GET /mcp/manifest/{claude|cursor|gemini|openai}.json` — готові конфіги
підключення; власні клієнти — через тегований `McpClientManifestGeneratorInterface`.
[server.json і маніфести клієнтів](../../../docs/discovery-UA.md)

### Тести

Юніт-, функціональні (бандл у тестовому застосунку) і наскрізні тести (офіційний клієнт `mcp/sdk` через HTTP на
2025-06-18, 2025-11-25 і 2026-07-28); юніт- і функціональні тести покривають 100% рядків `src/`. Додатково перевірено MCP
Inspector. [Тести](../../../docs/testing-UA.md)

## Оновлення з попередніх збірок

Стосується лише застосунків, що використовували бандл з `dev-main` до 1.0.0:

- Конфіг: `session_directory` (загальний і для групи) прибрано — сесії в кеш-пулі core; приберіть ключ, інакше
  «Unrecognized option». `groups` тепер може бути порожнім.
- Потрібен `ufo-tech/json-rpc-bundle` ^10.4.3 (`RpcExecutor` з 10.4.2; перетворення аргументів-enum і виправлення стану
  серіалізації — кілька `tools/call` в одному batch — з 10.4.3 разом з `ufo-tech/rpc-objects` 3.9.2).
- Імена маршрутів тепер містять групу (`<group>_ufo_mcp_endpoint`); ручний `name_prefix` у YAML-імпортах більше не потрібен.
- Назви й описи беруться лише з перекладів core: `ToolTextResolverInterface` і його реалізації (`Ufo\McpBundle\I18n\*`)
  прибрано.
- Класи перенесено з `Ufo\McpBundle\Services\*` до `Server\*`, `Server\Transport\*`, `Tool\*`; перевірки групи,
  доступності й токена перенесено з `McpController` до `EventListener\McpRequestListener`.

## Відомі проблеми

- **OAuth** не реалізовано: клієнти, яким потрібен OAuth, не підключаться (токен передається в заголовку).
- **Покриття під Xdebug 3.4.2** завершується segmentation fault на тестах, що запускають сервер SDK; використовуйте pcov.
