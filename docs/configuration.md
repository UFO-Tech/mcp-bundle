[Українська](configuration-UA.md) · [Documentation](README.md)

# Installation and configuration

## Install

```sh
composer require ufo-tech/mcp-bundle
```

Register the bundle next to the JSON-RPC bundle in `config/bundles.php`:

```php
Ufo\JsonRpcBundle\UfoJsonRpcBundle::class => ['all' => true],
Ufo\McpBundle\UfoMcpBundle::class => ['all' => true],
```

Copy `install/packages/ufo_mcp.yaml` to `config/packages/` and `install/routes/ufo_mcp.yaml` to `config/routes/`
(there is no Flex recipe yet).

Requirements: PHP >= 8.4, `ufo-tech/json-rpc-bundle` ^10.4.3, `mcp/sdk` ^0.8.1, Symfony 7.2+ / 8.

## Routes: one import per MCP endpoint

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

- `api_group` is the core API group the endpoint serves. Without it the group is the core default (`general`).
- Every import gets its own route names (`mcp_ufo_mcp_endpoint`, `internal_ufo_mcp_endpoint`, …) — no `name_prefix`
  needed. This works for YAML imports (the core loader); a PHP/XML route config needs a manual `name_prefix`.
- Each import serves:

| Route | Name | Methods |
| --- | --- | --- |
| `<prefix>` and `<prefix>/{ver}` | `<group>_ufo_mcp_endpoint` | `POST`, `DELETE`, `OPTIONS` (`GET` answers 405: no standalone server stream) |
| `<prefix>/server.json`, `<prefix>/{ver}/server.json` | `<group>_ufo_mcp_server_json[_ver]` | `GET` |
| `<prefix>/manifest/{aiName}.json`, `<prefix>/{ver}/manifest/{aiName}.json` | `<group>_ufo_mcp_manifest[_ver]` | `GET` |

`{ver}` is `v1`, `v2`, …; without it the version is `v1`. It is the core API version — see
[API versions](#api-versions).

## Config reference

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

- **`groups` is optional.** The group comes from the route; a group without an entry runs on the bundle-wide settings.
  An entry only overrides them, field by field (`mcp_server_info` too: a group may set only `name`).
- **There is no version setting** anywhere: `serverInfo.version` and `server.json` `version` are always the `{ver}` of
  the request.
- Removed options from pre-release builds (`session_directory`, `server_name`, `server_version`, `methods`) fail with
  "Unrecognized option".

## API groups

The group decides which methods become tools — the core `#[RPC\APIGroups]` attribute on the class or the method:

```php
use Ufo\RpcObject\RPC;

#[RPC\APIGroups(['mcp', 'internal'], withDefault: false)]
class CatalogProcedure implements IRpcService { /* tools in /mcp and /mcp/internal, not in /rpc */ }

#[RPC\APIGroups(['internal'], withDefault: false)]
class AdminProcedure implements IRpcService { /* only /mcp/internal */ }
```

The same group can be served by `/rpc/...` (core) and `/mcp/...` (this bundle) at once. Token validation per group
(`ufo_json_rpc.security.providers`) applies to MCP as well.

## API versions

`/mcp` and `/mcp/v1` serve version 1; `/mcp/v2` serves the core version 2 of the group
(`#[RPC\Info('catalog', version: 2, removedMethods: [...])]`; a version inherits the previous one). A version the group
does not have answers 404.

## Sessions and a shared cache

Clients of the session-based protocol (before 2026-07-28) get an `Mcp-Session-Id`. The session is stored in the core
cache pool `ufo_json_rpc.cache.pool`. It is a local filesystem pool by default; when several hosts serve MCP, point it
at a shared store (Redis etc.), otherwise a session created on one host is unknown on another. Stateless 2026-07-28
clients need no session storage.
