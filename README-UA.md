# UFO MCP Bundle
![Ukraine](https://img.shields.io/badge/Glory-Ukraine-yellow?labelColor=blue)
![License](https://img.shields.io/badge/license-MIT-green?labelColor=7b8185)

[English](README.md)

MCP-сервер (Model Context Protocol) для [`ufo-tech/json-rpc-bundle`](https://github.com/UFO-Tech/json-rpc-bundle):
методи вашого JSON-RPC API стають tools, які AI-агенти — Claude Code, Cursor, Gemini CLI, OpenAI — викликають через
Streamable HTTP. Як і `ufo-tech/rest-bundle`, це тонкий адаптер: бізнес-логіка лишається в RPC-сервісах, а кожен виклик
tool проходить через JSON-RPC bundle без змін.

Потрібні PHP 8.4+, Symfony 7.2+ / 8, `ufo-tech/json-rpc-bundle` ^10.4.3, офіційний
[`mcp/sdk`](https://github.com/modelcontextprotocol/php-sdk) ^0.8.1.

```bash
composer require ufo-tech/mcp-bundle
```

## Швидкий старт

1. Зареєструйте бандл у `config/bundles.php`:

   ```php
   Ufo\McpBundle\UfoMcpBundle::class => ['all' => true],
   ```

2. Скопіюйте `install/packages/ufo_mcp.yaml` у `config/packages/`, а `install/routes/ufo_mcp.yaml` — у `config/routes/`.
   Один імпорт маршрутів — один MCP-ендпоінт, що обслуговує одну групу API:

   ```yaml
   # config/routes/ufo_mcp.yaml
   ufo_mcp:
       resource: ../../vendor/ufo-tech/mcp-bundle/config/router.yaml
       prefix: /mcp
       defaults: { api_group: mcp }
   ```

3. Додайте RPC-методи в цю групу:

   ```php
   use Ufo\McpBundle\Attribute\McpTool;
   use Ufo\RpcObject\RPC;

   #[RPC\APIGroups(['mcp'], withDefault: false)]
   class CatalogProcedure implements IRpcService
   {
       /** Search products by name. */
       #[McpTool(readOnlyHint: true)]
       public function search(string $query, int $limit = 10): array { /* ... */ }
   }
   ```

4. Підключіть клієнта: `GET /mcp/manifest/claude.json` (або `cursor`, `gemini`, `openai`) повертає готовий конфіг;
   токен — RPC-токен JSON-RPC bundle (заголовок `Ufo-RPC-Token`).

## Можливості

- **Що стає tool — визначає група API.** Кожен метод групи — tool з назвою RPC-методу; кілька ендпоінтів
  (`/mcp`, `/mcp/internal`, …) поруч, кожен зі своїми методами — [налаштування](docs/configuration-UA.md)
- **Версії API.** `/mcp/v2` обслуговує версію 2 групи — [налаштування](docs/configuration-UA.md)
- **Роботу робить core.** Виклик tool — це RPC-запит: перетворення параметрів, DTO, валідація, кеш, блокування,
  RPC-події й помилки як у `/rpc` — [tools](docs/tools-UA.md)
- **Схеми й тексти для агентів.** `inputSchema` / `outputSchema` з RPC-метаданих; назви й описи з перекладів core за
  `Accept-Language`, зокрема для окремої групи; підказки `#[McpTool]` — [tools](docs/tools-UA.md)
- **Обидві епохи протоколу MCP.** Із сесіями (сесії в кеш-пулі core) і stateless 2026-07-28 на одному URL —
  [HTTP](docs/http-and-security-UA.md)
- **Та сама безпека.** RPC-токени core (401/403), 404 для вимкненої групи чи відсутньої версії, CORS і захист від DNS
  rebinding, ліміт тіла запиту — [безпека](docs/http-and-security-UA.md)
- **Discovery.** `server.json` для MCP Registry і маніфести клієнтів — [документи](docs/discovery-UA.md)
- **Розширюваність.** Перевірка доступності, політика доступу, власні маніфести клієнтів — [розширення](docs/extending-UA.md)

Не підтримується: OAuth, MCP resources і prompts, повідомлення з ініціативи сервера. Див.
[обмеження](docs/README-UA.md).

## Опис релізів

Усі версії, що додано і як цим користуватися: [releases](releases/README-UA.md). Остання: [1.0](releases/1.x/1.0/README-UA.md).

## Документація

Встановлення, налаштування і як працює бандл: [документація MCP Bundle](http://localhost/uk/docs/api/mcp-bundle).

## Розробка

PHP працює в Docker (`ufotech/php:8.4-fpm`):

```sh
make up-d              # запустити контейнер
make composer-install
make test              # юніт-, функціональні й наскрізні тести
```
