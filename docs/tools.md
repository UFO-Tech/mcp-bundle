[Українська](tools-UA.md) · [Documentation](README.md)

# Tools

Every RPC method of the endpoint's API group and API version is an MCP tool. `tools/list` returns them, `tools/call`
executes them through the core.

## Name

The tool name is the RPC method name (`Catalog.search`). MCP allows `^[a-zA-Z0-9_.-]{1,128}$`. A method of the group
whose name does not match (for example `#[RPC\Info('Bad', concat: ':')]` → `Bad:ping`) makes **every request of that
group** fail with HTTP 500; in debug the error says which method and why. Other groups and the rest of the application
are not affected. Methods outside MCP groups are never checked.

## Title and description

From the core translations, in the language negotiated from `Accept-Language` (core `RpcLocaleNegotiator`); the response
carries `Content-Language` and `Vary: Accept-Language`.

```
translations/rpc.uk.yaml        # base, all groups (core)
translations/rpc/mcp.uk.yaml    # only for the "mcp" group — e.g. texts written for an AI agent
```

```yaml
# translations/rpc/mcp.uk.yaml
Catalog.search:
    description: Пошук товарів за назвою. Повертає до limit товарів, найрелевантніші першими.
```

Each field falls back on its own: group + client language → group + default language → base + client language →
base + default language → `#[RPC\Info]` / PHPDoc. Without any title the title is the tool name. Translations are keyed by
method name, so they apply to every version of the method. Do not name a group like a Symfony Translator domain
(`messages`, `validators`, …).

## inputSchema

Built from the core method metadata — the same source as OpenRPC:

- parameters → `properties`; parameters without a default → `required`; defaults → `default`;
- `@param` descriptions → `description`;
- DTOs and enums → `$ref` to the tool's own `$defs` (OpenRPC `#/components/schemas/...` refs are rewritten, so every tool
  is self-contained, recursive DTOs included);
- `additionalProperties: false`.

The SDK validates `arguments` against the schema before anything runs: an unknown argument (including the core special
`$rpc` — so async/callback calls are not possible through MCP), a missing required one or a wrong type answers
JSON-RPC `-32602`.

## outputSchema

The method's return type, as the core describes it for OpenRPC, wrapped the way the result is returned:

```json
{"type": "object", "properties": {"result": <return type schema>}, "required": ["result"]}
```

- Every `"type": "array"` becomes `["array", "object"]`: a PHP array is a JSON list or a JSON object depending on its
  keys (`['a' => 1]` → `{"a":1}`), and clients that check `structuredContent` against the schema would reject the object.
- DTOs are `$ref`s to the tool's own `$defs`.
- No `outputSchema` when the method has no return type (or returns plain `object`): the core describes it as "any
  object", which would reject the scalar such a method may return.

## Annotations

Optional MCP `ToolAnnotations` hints with `#[McpTool]` on the method. It only adds hints; it does not decide exposure.

```php
use Ufo\McpBundle\Attribute\McpTool;

#[McpTool(readOnlyHint: true, idempotentHint: true)]
public function search(string $query, int $limit = 10): array
```

`readOnlyHint`, `destructiveHint`, `idempotentHint`, `openWorldHint` are `?bool`: `null` means "not specified" and is left
out.

## tools/call

The call becomes an `RpcRequest` (`RpcFromMcp`: method = tool name, params = arguments, API version of the route) and runs
through the core `RpcExecutor` — the same pipeline as `/rpc`: parameter conversion, DTOs, `#[RPC\Assertions]`,
`#[RPC\Cache]`, `#[RPC\Lock]`, RPC events. The response is normalized by the core serializer
(`RpcRequestHandler::responseToArray()`), exactly as `/rpc` returns it.

**Success:**

```json
{
  "content": [{"type": "text", "text": "{\"id\":7,\"name\":\"n7\"}"}],
  "structuredContent": {"result": {"id": 7, "name": "n7"}},
  "isError": false
}
```

`structuredContent` is for clients that read structured output (checked against `outputSchema`); the same JSON in a
`TextContent` is for clients that do not.

**Error** — an exception in the method, a validation error, or an exception thrown outside the method (an `rpc.request`
listener, etc.) — the core RPC error, `isError: true`, no `structuredContent`:

```json
{
  "content": [{"type": "text", "text": "{\"code\":-32603,\"message\":\"...\",\"data\":{\"exception\":\"Ufo\\\\RpcError\\\\RpcInternalException\"}}"}],
  "isError": true
}
```

`data` follows the core rules for the environment: full details (`file`, `line`, `trace`) in `dev` / `test`, only the
exception class and `extra` (validation details) elsewhere. Every error goes through the core `rpc.error` event and its
logging.

**Events and cache:**

- `rpc.post_response` fires on `kernel.terminate`, after the response has been sent, once per executed call — also for
  several `tools/call` in one JSON-RPC batch. None for an error response or a response served from the cache (core rule).
- `#[RPC\Cache]` works as on `/rpc`: a cached call does not execute the method.

The JSON-RPC envelope `id` of the MCP request is the client's; the internal RPC request id is generated per call and is
not exposed.
