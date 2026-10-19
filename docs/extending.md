[Українська](extending-UA.md) · [Documentation](README.md)

# Extending

Everything below is an ordinary Symfony service override or a tagged service; nothing requires changing the bundle.

## Availability of a group: `McpAvailabilityCheckerInterface`

Decides whether a group answers at all (otherwise 404). The default `DefaultMcpAvailabilityChecker` reads `enabled`
(`ufo_mcp.groups.<group>.enabled`, falling back to `ufo_mcp.enabled`). Replace it for a feature flag, a per-tenant
switch, a maintenance window:

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

Your implementation replaces the config check entirely (call the default one yourself if you need both).

## Hosts and origins: `McpAccessPolicyInterface`

CORS origins and the DNS-rebinding host allowlist — see [HTTP and security](http-and-security.md#cors-and-dns-rebinding).

## Client manifest for another client

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

Served at `<prefix>/manifest/acme.json` — the shape is whatever that client's config file expects. Use a key the bundle does not ship (`claude`, `cursor`, `gemini`, `openai`).

## Texts for the agent

Group translations (`translations/rpc/<group>.<locale>.yaml`) let you describe a method for an AI agent differently from
the OpenRPC docs, without touching the code — see [Tools](tools.md#title-and-description). An extension bundle ships
its own translations through the core `ufo_json_rpc.translations.paths`.

## Tool hints

`#[McpTool(readOnlyHint: …, destructiveHint: …, idempotentHint: …, openWorldHint: …)]` on an RPC method.

## Everything the core offers

A tool call is a core RPC request, so core extension points apply as they are: RPC event listeners (`rpc.request`,
`rpc.pre_execute`, `rpc.post_execute`, `rpc.pre_response`, `rpc.post_response`, `rpc.error`), parameter convertors,
token validators and per-group token providers, the response cache pool.
