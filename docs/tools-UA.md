[English](tools.md) · [Документація](README-UA.md)

# Tools

Кожен RPC-метод групи API та версії API ендпоінта — це MCP tool. `tools/list` повертає їх, `tools/call`
виконує їх через core.

## Назва

Назва tool — це назва RPC-методу (`Catalog.search`). MCP дозволяє `^[a-zA-Z0-9_.-]{1,128}$`. Метод групи,
чия назва не відповідає шаблону (наприклад, `#[RPC\Info('Bad', concat: ':')]` → `Bad:ping`), змушує **кожен запит цієї
групи** завершуватися HTTP 500; у debug помилка вказує, який метод і чому. Інші групи та решту застосунку
це не зачіпає. Методи поза MCP-групами ніколи не перевіряються.

## Заголовок і опис

Беруться з перекладів core мовою, узгодженою за `Accept-Language` (`RpcLocaleNegotiator` core); відповідь
містить `Content-Language` і `Vary: Accept-Language`.

```
translations/rpc.uk.yaml        # base, all groups (core)
translations/rpc/mcp.uk.yaml    # only for the "mcp" group — e.g. texts written for an AI agent
```

```yaml
# translations/rpc/mcp.uk.yaml
Catalog.search:
    description: Пошук товарів за назвою. Повертає до limit товарів, найрелевантніші першими.
```

Кожне поле має власний ланцюжок fallback: група + мова клієнта → група + типова мова → базовий + мова клієнта →
базовий + типова мова → `#[RPC\Info]` / PHPDoc. Якщо заголовка немає ніде, заголовком стає назва tool. Ключ перекладів —
назва методу, тож вони діють для всіх версій методу. Не називайте групу так само, як домен Symfony Translator
(`messages`, `validators`, …).

## inputSchema

Будується з метаданих методу в core — того самого джерела, що й OpenRPC:

- параметри → `properties`; параметри без значення за замовчуванням → `required`; значення за замовчуванням → `default`;
- описи з `@param` → `description`;
- DTO та enum → `$ref` на власні `$defs` tool (посилання OpenRPC `#/components/schemas/...` переписуються, тож кожен tool
  самодостатній, включно з рекурсивними DTO);
- `additionalProperties: false`.

SDK валідує `arguments` за схемою ще до виконання: невідомий аргумент (зокрема спеціальний `$rpc` core — тому
async/callback-виклики через MCP неможливі), відсутній обов'язковий або неправильний тип дають відповідь
JSON-RPC `-32602`.

## outputSchema

Тип повернення методу в тому вигляді, як core описує його для OpenRPC, обгорнутий так само, як повертається результат:

```json
{"type": "object", "properties": {"result": <return type schema>}, "required": ["result"]}
```

- Кожен `"type": "array"` перетворюється на `["array", "object"]`: PHP-масив стає JSON-списком або JSON-об'єктом залежно від
  ключів (`['a' => 1]` → `{"a":1}`), і клієнти, які перевіряють `structuredContent` за схемою, відхилили б об'єкт.
- DTO — це `$ref` на власні `$defs` tool.
- `outputSchema` немає, коли метод не має типу повернення (або повертає просто `object`): core описує його як «будь-який
  об'єкт», а така схема відхилила б скаляр, який може повернути такий метод.

## Анотації

Необов'язкові підказки MCP `ToolAnnotations` через `#[McpTool]` на методі. Атрибут лише додає підказки; він не впливає на публікацію.

```php
use Ufo\McpBundle\Attribute\McpTool;

#[McpTool(readOnlyHint: true, idempotentHint: true)]
public function search(string $query, int $limit = 10): array
```

`readOnlyHint`, `destructiveHint`, `idempotentHint`, `openWorldHint` мають тип `?bool`: `null` означає «не задано» і
не потрапляє у вивід.

## tools/call

Виклик перетворюється на `RpcRequest` (`RpcFromMcp`: method = назва tool, params = аргументи, версія API з маршруту) і
виконується через `RpcExecutor` core — той самий конвеєр, що й `/rpc`: конвертація параметрів, DTO, `#[RPC\Assertions]`,
`#[RPC\Cache]`, `#[RPC\Lock]`, RPC-події. Відповідь нормалізує серіалізатор core
(`RpcRequestHandler::responseToArray()`) — точно так, як її повертає `/rpc`.

**Успіх:**

```json
{
  "content": [{"type": "text", "text": "{\"id\":7,\"name\":\"n7\"}"}],
  "structuredContent": {"result": {"id": 7, "name": "n7"}},
  "isError": false
}
```

`structuredContent` — для клієнтів, які читають структурований вивід (перевіряється за `outputSchema`); той самий JSON у
`TextContent` — для клієнтів, які цього не вміють.

**Помилка** — виняток у методі, помилка валідації або виняток, кинутий поза методом (слухач `rpc.request`
тощо), — це RPC-помилка core, `isError: true`, без `structuredContent`:

```json
{
  "content": [{"type": "text", "text": "{\"code\":-32603,\"message\":\"...\",\"data\":{\"exception\":\"Ufo\\\\RpcError\\\\RpcInternalException\"}}"}],
  "isError": true
}
```

`data` формується за правилами core для середовища: повні деталі (`file`, `line`, `trace`) у `dev` / `test`, в інших —
лише клас винятку та `extra` (деталі валідації). Кожна помилка проходить через подію core `rpc.error` і його
логування.

**Події та кеш:**

- `rpc.post_response` спрацьовує на `kernel.terminate`, після відправлення відповіді, один раз на кожен виконаний виклик —
  і для кількох `tools/call` в одному JSON-RPC batch. Для відповіді з помилкою чи відповіді з кешу не спрацьовує (правило core).
- `#[RPC\Cache]` працює так само, як на `/rpc`: закешований виклик не виконує метод.

`id` JSON-RPC-конверта MCP-запиту належить клієнту; внутрішній id RPC-запиту генерується для кожного виклику й
назовні не віддається.
