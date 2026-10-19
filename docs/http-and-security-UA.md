[English](http-and-security.md) · [Документація](README-UA.md)

# HTTP, сесії та безпека

## Версії протоколу

Транспорт — Streamable HTTP-транспорт із `mcp/sdk`. Він обслуговує обидві епохи протоколу на тому самому URL:

- **Session-based** (2024-11-05 … 2025-11-25): `initialize` → `Mcp-Session-Id` → запити з цим заголовком; `DELETE`
  завершує сесію. Сесії зберігаються в пулі кешу core (див. [конфігурацію](configuration-UA.md#сесії-та-спільний-кеш)).
  Запит без сесії отримує 400, невідома чи прострочена сесія — 404 (клієнт виконує initialize повторно).
- **Stateless 2026-07-28**: без handshake і без сесії. Кожен запит містить
  `params._meta["io.modelcontextprotocol/protocolVersion"]` і заголовки `MCP-Protocol-Version`, `Mcp-Method` (а для
  `tools/call` — ще `Mcp-Name`); результат містить `_meta["io.modelcontextprotocol/serverInfo"]`.

Перевірено end to end офіційним клієнтом SDK через HTTP на версіях 2025-06-18, 2025-11-25 і 2026-07-28, а також через MCP Inspector
(TypeScript SDK).

`GET` (окремий SSE-потік від сервера до клієнта) відповідає 405: сервер нічого не надсилає з власної ініціативи.
JSON-RPC batch приймаються.

## Порядок перевірок

`McpRequestListener` виконується перед кожною дією `McpController`:

1. **404** з порожнім тілом — група вимкнена (`McpAvailabilityCheckerInterface`, типово — `enabled`) або в
   групи немає методів у запитаній `{ver}`. Ця перевірка йде до перевірки токена, тож недоступний ендпоінт виглядає
   однаково з токеном і без нього.
2. **OPTIONS** (CORS preflight) проходить без токена; на нього відповідає транспорт.
3. **Токен** — `IRpcSecurity` core:
   - MCP-ендпоінт: `isValidApiRequest()` (`protected_api`, валідатори токенів, `security.providers` для групи);
   - `server.json` і маніфести: `isValidDocRequest()` — захищені лише коли ввімкнено `protected_doc`;
   - немає токена → **401** `{"error": "Unauthorized. Token not found"}`; недійсний → **403** `{"error": "Forbidden. Invalid token"}`.

Дія визначається за методом контролера, а не за назвою маршруту, тож `name_prefix` в імпорті не дозволяє обійти
перевірку.

## Заголовок із токеном

Заголовок токена core (типово `Ufo-RPC-Token`, `ufo_json_rpc.security.token_name`). CORS-заголовок
`Access-Control-Allow-Headers` містить його разом із заголовками MCP. Клієнти, що підтримують власні заголовки (Claude
Code, Cursor, Gemini CLI, OpenAI Responses API), надсилають його — [маніфести клієнтів](discovery-UA.md) прописують його на місці.

**OAuth не реалізовано.** Немає `/.well-known/oauth-protected-resource` і немає `WWW-Authenticate` у відповіді 401. MCP
робить авторизацію необов'язковою, але клієнти, які підтримують лише OAuth (наприклад, MCP Inspector без заголовка, який тоді
запускає свій OAuth-flow), підключитися не зможуть.

## CORS і DNS rebinding

`McpAccessPolicyInterface` (типово `DefaultMcpAccessPolicy`) керує двома шарами транспорту SDK:

- `getAllowedOrigins()` — CORS. Типово `['*']`; `[]` — CORS-заголовки не надсилаються.
- `getAllowedHosts()` — захист від DNS rebinding. Типово `[]` = вимкнено. Якщо список задано, запит, чий `Host` у ньому
  відсутній, отримує 403. **Коли запит має заголовок `Origin`, SDK перевіряє хост з Origin за тим самим списком**, тож
  хост браузерного клієнта теж має бути в списку.

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

## Ліміт тіла запиту

`max_body_bytes` (для кожної групи) — на більше тіло запиту відповідь 413 із JSON-RPC-помилкою.
