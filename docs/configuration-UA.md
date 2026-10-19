[English](configuration.md) · [Документація](README-UA.md)

# Встановлення та конфігурація

## Встановлення

```sh
composer require ufo-tech/mcp-bundle
```

Зареєструйте бандл поруч із JSON-RPC bundle у `config/bundles.php`:

```php
Ufo\JsonRpcBundle\UfoJsonRpcBundle::class => ['all' => true],
Ufo\McpBundle\UfoMcpBundle::class => ['all' => true],
```

Скопіюйте `install/packages/ufo_mcp.yaml` у `config/packages/`, а `install/routes/ufo_mcp.yaml` — у `config/routes/`
(Flex-рецепта поки немає).

Вимоги: PHP >= 8.4, `ufo-tech/json-rpc-bundle` ^10.4.3, `mcp/sdk` ^0.8.1, Symfony 7.2+ / 8.

## Маршрути: один імпорт на MCP-ендпоінт

```yaml
# config/routes/ufo_mcp.yaml
ufo_mcp:
    resource: ../../vendor/ufo-tech/mcp-bundle/config/router.yaml
    prefix: /mcp
    defaults: { api_group: mcp }

ufo_mcp_internal:
    resource: ../../vendor/ufo-tech/mcp-bundle/config/router.yaml
    prefix: /mcp/internal
    defaults: { api_group: internal }
```

- `api_group` — група API core, яку обслуговує ендпоінт. Без нього група — типова група core (`general`).
- Кожен імпорт отримує власні назви маршрутів (`mcp_ufo_mcp_endpoint`, `internal_ufo_mcp_endpoint`, …) — `name_prefix`
  не потрібен. Це працює для YAML-імпортів (завантажувач core); для конфігурації маршрутів у PHP/XML потрібен ручний `name_prefix`.
- Кожен імпорт обслуговує:

| Маршрут | Назва | Методи |
| --- | --- | --- |
| `<prefix>` і `<prefix>/{ver}` | `<group>_ufo_mcp_endpoint` | `POST`, `DELETE`, `OPTIONS` (`GET` відповідає 405: окремого серверного потоку немає) |
| `<prefix>/server.json`, `<prefix>/{ver}/server.json` | `<group>_ufo_mcp_server_json[_ver]` | `GET` |
| `<prefix>/manifest/{aiName}.json`, `<prefix>/{ver}/manifest/{aiName}.json` | `<group>_ufo_mcp_manifest[_ver]` | `GET` |

`{ver}` — це `v1`, `v2`, …; без нього версія — `v1`. Це версія API core — див.
[Версії API](#версії-api).

## Довідник конфігурації

```yaml
ufo_mcp:
    enabled: true                 # bool; '%env(bool:MCP_ENABLED)%' works
    instructions: ~               # MCP "instructions" returned by initialize
    max_body_bytes: 4194304       # request body limit, >= 1 (413 above it)
    session_ttl: 3600             # seconds a session of the session-based protocol lives, >= 1
    mcp_server_info:              # server identity: initialize serverInfo and server.json
        name: 'io.github.your-org/your-app'   # reverse-DNS id for the MCP Registry; default "ufo-rpc"
        title: 'Your App MCP Server'
        description: 'Exposes Your App RPC services as MCP tools.'   # server.json only
        icons:                                # server.json only
            - src: 'https://example.com/icon.png'   # required
              mime_type: 'image/png'
              sizes: ['128x128']
              theme: ~                              # light | dark
    groups:                       # optional
        internal:                 # = the api_group of a route import
            enabled: ~            # every field: ~ (or omitted) inherits the bundle-wide value
            instructions: 'Internal tools.'
            max_body_bytes: ~
            session_ttl: ~
            mcp_server_info:
                name: 'io.github.your-org/your-app-internal'   # unset fields inherit field by field
```

- **`groups` — необов'язковий.** Група береться з маршруту; група без власного запису працює на загальних налаштуваннях
  бандла. Запис лише перевизначає їх, поле за полем (`mcp_server_info` теж: група може задати лише `name`).
- **Налаштування версії немає** ніде: `serverInfo.version` і `version` у `server.json` — це завжди `{ver}`
  запиту.
- Опції, вилучені з попередніх збірок (`session_directory`, `server_name`, `server_version`, `methods`), падають із
  "Unrecognized option".

## Групи API

Група визначає, які методи стають tools, — це атрибут core `#[RPC\APIGroups]` на класі чи методі:

```php
use Ufo\RpcObject\RPC;

#[RPC\APIGroups(['mcp', 'internal'], withDefault: false)]
class CatalogProcedure implements IRpcService { /* tools in /mcp and /mcp/internal, not in /rpc */ }

#[RPC\APIGroups(['internal'], withDefault: false)]
class AdminProcedure implements IRpcService { /* only /mcp/internal */ }
```

Одну й ту саму групу можуть одночасно обслуговувати `/rpc/...` (core) і `/mcp/...` (цей бандл). Перевірка токена
для групи (`ufo_json_rpc.security.providers`) діє і для MCP.

## Версії API

`/mcp` і `/mcp/v1` обслуговують версію 1; `/mcp/v2` обслуговує версію 2 групи в core
(`#[RPC\Info('catalog', version: 2, removedMethods: [...])]`; версія успадковує попередню). Версія, якої в групи
немає, відповідає 404.

## Сесії та спільний кеш

Клієнти session-based протоколу (до 2026-07-28) отримують `Mcp-Session-Id`. Сесія зберігається в пулі кешу core
`ufo_json_rpc.cache.pool`. Типово це локальний пул на файловій системі; коли MCP обслуговують кілька хостів, спрямуйте його
на спільне сховище (Redis тощо), інакше сесія, створена на одному хості, буде невідома на іншому. Stateless-клієнтам
2026-07-28 сховище сесій не потрібне.
