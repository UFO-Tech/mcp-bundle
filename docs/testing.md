[Українська](testing-UA.md) · [Documentation](README.md)

# Tests

PHP runs in Docker (`ufotech/php:8.4-fpm`, see `docker-compose.yaml`):

```sh
make up-d
make composer-install
make test                                  # all suites
make test ARGS="--testsuite Unit"          # one suite
```

| Suite | What it checks | How |
| --- | --- | --- |
| `Unit` | config tree and typed config, `server.json`, client manifests, session store, request carrier, tool executor, route import, default extension points | plain PHPUnit, mocks |
| `Functional` | the bundle inside an application: routing, 404/401/403, initialize, tools/list (groups, versions, translations, schemas, annotations), tools/call (results, errors, events, cache, batch), sessions, stateless protocol, CORS, DNS rebinding, body limit, `server.json`, manifests, extension points | `TestKernel` (`tests/Fixtures`), HTTP requests through `handle()` + `terminate()` |
| `E2E` | the official `mcp/sdk` client over real HTTP in three protocol versions | PHP built-in server with `tests/Fixtures/public/index.php` |

The fixture application (`tests/Fixtures`): procedures in the groups `mcp`, `internal`, `off`, `bad`, a v2 procedure,
translations, routes like an application's `config/routes/ufo_mcp.yaml`. `TestKernel::$mcpConfig` and
`TestKernel::$serviceOverrides` change the config or replace a service for one test.

## Coverage

With Xdebug 3.4.2 (the `ufotech/php:8.4-fpm` image) coverage of the tests that run the SDK server ends in a segmentation fault; use pcov:

```sh
docker exec php_mcp pecl install pcov     # once per container
docker exec -w /app -e XDEBUG_MODE=off php_mcp php -d extension=pcov.so -d pcov.enabled=1 \
    vendor/bin/phpunit --testsuite Unit,Functional --coverage-text
```

## Checking with MCP Inspector

```sh
docker exec -d -w /app php_mcp php -S 0.0.0.0:8099 tests/Fixtures/public/index.php
docker run --rm --network container:php_mcp node:22-alpine npx -y @modelcontextprotocol/inspector --cli \
    http://127.0.0.1:8099/mcp --transport http --header "Ufo-RPC-Token: test-token" --method tools/list
```
