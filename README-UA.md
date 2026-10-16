# UFO MCP Bundle

Окремий Symfony-бандл, який відкриває наявні UFO JSON-RPC методи як MCP tools.
Архітектура аналогічна rest-bundle: адаптер використовує ServiceMap та
RpcRequestHandler, зберігаючи валідацію, DTO-конвертацію, кеш і події RPC.

Потрібні PHP >= 8.4 та json-rpc-bundle ^10.2. Протокол і Streamable HTTP
реалізує офіційний mcp/sdk ^0.8.1. Підтримуються клієнти з initialize/сесіями
та stateless MCP 2026-07-28.

## Підключення

До публікації пакета додайте Composer path repository на цей checkout та
встановіть `ufo-tech/mcp-bundle:@dev`. Після публікації:

```sh
composer require ufo-tech/mcp-bundle
```

У config/bundles.php додайте:

```php
Ufo\McpBundle\UfoMcpBundle::class => ['all' => true],
```

Скопіюйте install/packages/ufo_mcp.yaml та install/routes/ufo_mcp.yaml у
відповідні директорії config застосунку. Автоматичного Flex recipe поки немає.

```yaml
ufo_mcp:
    path: /mcp
    api_group: general
    api_version: v1
    methods: [catalog.search, catalog.get]
    allowed_hosts: [api.example.com]
```

Непорожній methods визначає точний список відкритих RPC-методів. Якщо список
порожній, відкриваються лише методи з атрибутом:

```php
use Ufo\McpBundle\Attribute\McpTool;

#[McpTool(description: 'Пошук товарів за назвою.', readOnlyHint: true)]
public function search(string $query, int $limit = 10): array
{
    // Наявна бізнес-логіка.
}
```

Атрибут підтримує name, title, description, readOnlyHint, destructiveHint,
idempotentHint та openWorldHint. Список methods використовує RPC-назви,
навіть якщо атрибут задає іншу MCP-назву. Колізії та некоректні назви
відхиляються. Схеми параметрів, DTO, enum, required та default беруться
з метаданих RPC; посилання на схеми перетворюються на локальні $defs.
Наявні атрибути конвертації RPC, зокрема RPC\Param для enum, залишаються потрібними.

## Доступ і результати

На кожному запиті, окрім CORS preflight, перевіряється наявний IRpcSecurity:
токен API, protected_api та провайдер відповідної API-групи. Для захищеного
endpoint увімкніть ufo_json_rpc.security.protected_api. Заголовок за
замовчуванням — Ufo-RPC-Token. Власний OAuth-сервер та OAuth discovery
цей пакет не надає; це потрібно врахувати для клієнтів, які вимагають OAuth.

allowed_hosts задає допустимі Host/Origin hostname, allowed_origins — повні
Origin URL для CORS. За замовчуванням дозволені лише loopback hosts,
браузерний CORS вимкнений. Для браузерного клієнта налаштуйте обидва списки.

Успішний виклик повертає JSON у text content та structuredContent з об'єктом
{"result": ...}. Помилка виконання повертається з isError: true. Виклики
синхронні; async/callback керування RPC, resources та prompts не експортуються.

Сесії старіших клієнтів зберігаються у session_directory
(типово %kernel.cache_dir%/ufo_mcp_sessions), session_ttl — 3600 секунд.
Кілька worker-процесів мають використовувати спільну директорію, кілька
серверів — спільний volume або власну фабрику сервера. Для stateless-клієнтів
сесії не потрібні. Ліміт тіла запиту max_body_bytes — 4 MiB.
enabled: false прибирає MCP route.

## Конектор і плагін

Готовий шаблон Claude Code плагіна — install/claude-code-plugin. Він містить
manifest і MCP-конфігурацію без секретів. Задайте змінні оточення перед
запуском клієнта:

```sh
export UFO_MCP_URL=https://api.example.com/mcp
export UFO_MCP_TOKEN=your-api-token
claude --plugin-dir ./vendor/ufo-tech/mcp-bundle/install/claude-code-plugin
```

Цю директорію можна скопіювати та адаптувати для власного API. Для прямого
підключення Claude Code використайте її .mcp.json; для Cursor —
install/clients/cursor.json. Якщо змінено token_name, змініть заголовок у
клієнтській конфігурації. Клієнти використовують один і той самий MCP endpoint.

Повна конфігурація й деталі — у [README англійською](README.md).

## Перевірка

```sh
composer install
vendor/bin/phpunit
```

Інтеграційні тести запускають Symfony kernel із JSON-RPC бандлом та
виконують справжні MCP HTTP-запити через SDK і RPC pipeline.
