[English](architecture.md) · [Документація](README-UA.md)

# Архітектура

## Принципи

1. **Тонкий адаптер над JSON-RPC bundle.** Як і `ufo-tech/rest-bundle`, цей бандл додає протокол, а не другий
   сервер. Бізнес-логіка лишається в RPC-сервісах (`IRpcService`); виклик tool — це звичайний RPC-запит, який виконує
   core. Усе, що core робить для `/rpc` — конвертацію параметрів, DTO, `#[RPC\Assertions]`, кеш, блокування, RPC-події,
   формат помилок, — воно робить і для MCP.
2. **Перевикористовувати, ніколи не дублювати.** Карта сервісів, групи API, версії, переклади, узгодження локалі,
   безпека на токенах, пули кешу, імпорт маршрутів і виконання запитів — усе береться з core. Коли в core бракує точки
   розширення, виправлення йде в core (так з'явилися `IApiRouteImport` у 10.4.1 та `RpcExecutor` у 10.4.2), а не в
   копію тут.
3. **Маршрут — джерело істини.** Група API береться з імпорту маршрутів (`api_group`), версія API — із сегмента
   маршруту `{ver}`. Конфігурація лише налаштовує сервер групи; вона ніколи не вирішує, які методи існують і яка
   версія обслуговується.
4. **Публікація = членство в групі API.** Кожен метод групи стає tool. Немає ні allowlist, ні
   окремої реєстрації для MCP: `#[RPC\APIGroups]` уже визначає, де опубліковано метод.
5. **Протокол реалізує офіційний MCP SDK.** Handshake, сесії, stateless-протокол 2026-07-28, валідація аргументів
   за `inputSchema`, batching, middleware для CORS і DNS rebinding — це `mcp/sdk` (`^0.8.1`). Бандл
   будує сервер SDK на кожен запит і підключає до нього RPC-методи.
6. **Падати голосно.** Метод, чия назва не є коректною назвою MCP tool, ламає свою групу (HTTP 500 з поясненням у
   debug), а не тихо зникає зі списку tools.

## Компоненти

| Компонент | Роль |
| --- | --- |
| `Routing\McpRouteImport` | Реєструє `config/router.yaml` у завантажувачі core (`IApiRouteImport`): кожен імпорт отримує назви маршрутів `<group>_ufo_mcp_*` і `_mcp_name_prefix` |
| `Controller\McpController` | Три дії: MCP-ендпоінт, `server.json`, маніфести клієнтів |
| `EventListener\McpRequestListener` | Фільтр перед кожною дією `McpController`: група API, доступність (404), токен (401/403) |
| `Server\McpHandler` | Узгодження локалі, транспорт, сервер SDK, `Content-Language` / `Vary` |
| `Server\Transport\StreamableHttpTransportFactory` | Streamable HTTP-транспорт SDK із CORS, захистом від DNS rebinding і лімітом тіла запиту групи |
| `Server\McpServerFactory` | Сервер SDK для одного запиту: ідентичність, інструкції, сховище сесій, один tool на кожен RPC-метод групи й версії |
| `Server\Session\RpcCacheSessionStore` | Сесії session-based протоколу в пулі кешу core `rpc.cache.pool` |
| `Tool\ToolSchemaFactory` | MCP `Tool` із метаданих `Service` core: перевірка назви, тексти, `inputSchema`, `outputSchema`, анотації; кешується в `RpcMetadataCache` core |
| `Tool\RpcToolHandler` / `Tool\RpcToolExecutor` | `tools/call` → `RpcExecutor` core → MCP `CallToolResult` |
| `Server\RequestPrepare\Holders\RpcFromMcp` | Носій запиту: будує `RpcRequest` із tool, його аргументів і версії |
| `DocAdapters\Outputs\ServerJsonAdapter` | `server.json` для MCP Registry |
| `ClientManifest\*` | Конфігурації підключення клієнтів (Claude Code, Cursor, Gemini CLI, OpenAI), tagged locator |
| `ConfigService\McpConfig` / `McpGroupConfig` / `McpServerInfoConfig` | Типізоване подання конфігурації `ufo_mcp`; група без власного запису отримує загальні налаштування бандла |
| `Security\McpAccessPolicyInterface`, `Availability\McpAvailabilityCheckerInterface` | Точки розширення (див. [Розширення](extending-UA.md)) |

## Шлях запиту

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

## Що будується на кожен запит, що кешується

- Сервер SDK і його список tools створюються для кожного HTTP-запиту (між запитами сервер не зберігає стану;
  сесії живуть у пулі кешу).
- Кожен `Tool` кешується в `RpcMetadataCache` core (`cache.system`) з ключем за групою, версією, методом і локаллю.
  Кеш скидається при кожному перезбиранні контейнера й вимкнений у debug — те саме правило, що core застосовує до власної
  документації. Compiler pass свідомо не використовується: контейнер не відстежує класи DTO, а для побудови схем потрібні
  сервіси застосунку.
- Сесії (лише для session-based протоколу) живуть у `rpc.cache.pool` під ключем `ufo_mcp_<group>_<session id>` з
  `session_ttl` на кожен запис.
