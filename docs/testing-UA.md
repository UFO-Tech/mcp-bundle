[English](testing.md) · [Документація](README-UA.md)

# Тести

PHP запускається в Docker (`ufotech/php:8.4-fpm`, див. `docker-compose.yaml`):

```sh
make up-d
make composer-install
make test                                  # all suites
make test ARGS="--testsuite Unit"          # one suite
```

| Набір | Що перевіряє | Як |
| --- | --- | --- |
| `Unit` | дерево конфігурації та типізовану конфігурацію, `server.json`, маніфести клієнтів, сховище сесій, носій запиту, виконавця tools, імпорт маршрутів, типові точки розширення | звичайний PHPUnit, моки |
| `Functional` | бандл усередині застосунку: маршрутизацію, 404/401/403, initialize, tools/list (групи, версії, переклади, схеми, анотації), tools/call (результати, помилки, події, кеш, batch), сесії, stateless-протокол, CORS, DNS rebinding, ліміт тіла запиту, `server.json`, маніфести, точки розширення | `TestKernel` (`tests/Fixtures`), HTTP-запити через `handle()` + `terminate()` |
| `E2E` | офіційний клієнт `mcp/sdk` через справжній HTTP у трьох версіях протоколу | вбудований сервер PHP із `tests/Fixtures/public/index.php` |

Застосунок-фікстура (`tests/Fixtures`): процедури в групах `mcp`, `internal`, `off`, `bad`, процедура v2,
переклади, маршрути як у `config/routes/ufo_mcp.yaml` застосунку. `TestKernel::$mcpConfig` і
`TestKernel::$serviceOverrides` змінюють конфігурацію або замінюють сервіс для одного тесту.

## Покриття

З Xdebug 3.4.2 (образ `ufotech/php:8.4-fpm`) збирання покриття для тестів, що запускають сервер SDK, закінчується segmentation fault; використовуйте pcov:

```sh
docker exec php_mcp pecl install pcov     # once per container
docker exec -w /app -e XDEBUG_MODE=off php_mcp php -d extension=pcov.so -d pcov.enabled=1 \
    vendor/bin/phpunit --testsuite Unit,Functional --coverage-text
```

## Перевірка через MCP Inspector

```sh
docker exec -d -w /app php_mcp php -S 0.0.0.0:8099 tests/Fixtures/public/index.php
docker run --rm --network container:php_mcp node:22-alpine npx -y @modelcontextprotocol/inspector --cli \
    http://127.0.0.1:8099/mcp --transport http --header "Ufo-RPC-Token: test-token" --method tools/list
```
