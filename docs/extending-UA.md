[English](extending.md) · [Документація](README-UA.md)

# Розширення

Усе нижче — звичайне перевизначення Symfony-сервісу або тегований сервіс; змінювати бандл не потрібно.

## Доступність групи: `McpAvailabilityCheckerInterface`

Вирішує, чи група взагалі відповідає (інакше 404). Типовий `DefaultMcpAvailabilityChecker` читає `enabled`
(`ufo_mcp.groups.<group>.enabled` з fallback на `ufo_mcp.enabled`). Замініть його для feature flag, перемикача
на рівні тенанта, вікна обслуговування:

```php
final class FeatureFlagAvailability implements McpAvailabilityCheckerInterface
{
    public function __construct(private FeatureFlags $flags) {}

    public function isEnabled(string $group): bool
    {
        return $this->flags->isOn('mcp.'.$group);
    }
}
```

```yaml
Ufo\McpBundle\Availability\McpAvailabilityCheckerInterface: '@App\Mcp\FeatureFlagAvailability'
```

Ваша реалізація повністю замінює перевірку з конфігурації (якщо потрібні обидві, викличте типову самостійно).

## Хости та origins: `McpAccessPolicyInterface`

CORS origins і allowlist хостів для захисту від DNS rebinding — див. [HTTP і безпека](http-and-security-UA.md#cors-і-dns-rebinding).

## Маніфест для іншого клієнта

```php
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\McpBundle\ClientManifest\McpClientManifestGeneratorHolder;
use Ufo\McpBundle\ClientManifest\McpClientManifestGeneratorInterface;
use Ufo\McpBundle\ConfigService\McpGroupConfig;

#[AutoconfigureTag(McpClientManifestGeneratorHolder::TAG, ['key' => 'acme'])]
final class AcmeAgentManifestGenerator implements McpClientManifestGeneratorInterface
{
    public function generate(McpGroupConfig $group, string $endpointUrl, string $tokenHeaderName): array
    {
        return ['servers' => [$group->name => ['url' => $endpointUrl, 'headers' => [$tokenHeaderName => '<UFO_MCP_TOKEN>']]]];
    }
}
```

Віддається за адресою `<prefix>/manifest/acme.json` — формат такий, якого очікує конфігураційний файл цього клієнта. Використовуйте ключ, якого немає серед вбудованих у бандл (`claude`, `cursor`, `gemini`, `openai`).

## Тексти для агента

Переклади групи (`translations/rpc/<group>.<locale>.yaml`) дають змогу описати метод для AI-агента інакше, ніж у
документації OpenRPC, не чіпаючи код — див. [Tools](tools-UA.md#заголовок-і-опис). Бандл-розширення постачає
власні переклади через `ufo_json_rpc.translations.paths` core.

## Підказки для tools

`#[McpTool(readOnlyHint: …, destructiveHint: …, idempotentHint: …, openWorldHint: …)]` на RPC-методі.

## Усе, що пропонує core

Виклик tool — це RPC-запит core, тож точки розширення core діють без змін: слухачі RPC-подій (`rpc.request`,
`rpc.pre_execute`, `rpc.post_execute`, `rpc.pre_response`, `rpc.post_response`, `rpc.error`), конвертори параметрів,
валідатори токенів і провайдери токенів для груп, пул кешу відповідей.
