<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tool;

use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\JsonRpcBundle\DocAdapters\Traits\JsonSchemaDtoFormatTrait;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\JsonRpcBundle\Server\RpcCache\RpcMetadataCache;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\EnumProcessor\EnumsHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\McpBundle\Attribute\McpTool;
use Ufo\RpcError\RpcInternalException;

use function preg_match;
use function sprintf;

/**
 * Builds the MCP Tool for one RPC method of the current API group. The result is kept in
 * the core RpcMetadataCache (reset on every container rebuild, disabled in debug), keyed
 * by group, version, method and locale: the same method name may have another
 * implementation or other texts in another group.
 *
 * Title/description come from $service itself — ServiceMap::getServices() already
 * returns the services of the current group with that group's translations
 * (translations/rpc/<group>.<locale>.yaml over translations/rpc.<locale>.yaml).
 *
 * The MCP tool-name rule is checked here, not for every RPC method of the app: only
 * methods that actually become tools must comply.
 */
final class ToolSchemaFactory
{
    use JsonSchemaDtoFormatTrait;

    private const string NAME_PATTERN = '/^[a-zA-Z0-9_.-]{1,128}$/D';

    /** The core return schema of a method without a return type (and of "object"). */
    private const array ANY_OBJECT = [T::TYPE => 'object', T::ADDITIONAL_PROPERTIES => true];

    public function __construct(
        private readonly ChainParamConvertor $paramConvertor,
        private readonly EnumsHolder $enumsHolder,
        private readonly RpcMetadataCache $metadataCache,
        private readonly ApiGroupContext $apiGroupContext,
    ) {}

    public function create(string $name, Service $service, string $version, ?string $locale): Tool
    {
        $key = 'mcp.tool.'.$this->apiGroupContext->getGroup().':'.$version.':'.$name.':'.($locale ?? '-');

        return $this->metadataCache->get($key, Tool::class, fn(): Tool => new Tool(
            name: $this->validName($name),
            title: $service->getTitle($locale) ?? $name,
            inputSchema: $this->buildInputSchema($service),
            description: $service->getDescription($locale),
            annotations: $this->buildAnnotations($service),
            outputSchema: $this->buildOutputSchema($service),
        ));
    }

    /**
     * @throws RpcInternalException
     */
    private function validName(string $name): string
    {
        if (!preg_match(self::NAME_PATTERN, $name)) {
            throw new RpcInternalException(sprintf(
                'RPC method "%s" cannot be exposed as an MCP tool: its name does not match the MCP tool name pattern (%s).',
                $name,
                self::NAME_PATTERN,
            ));
        }
        return $name;
    }

    private function buildInputSchema(Service $service): array
    {
        $this->schemas = [];
        $properties = [];
        $required = [];
        foreach ($service->getParams() as $param) {
            $schema = $this->schemaForParam($param, $service);
            if ($param->description !== '') {
                $schema['description'] = $param->description;
            }
            if ($param->isOptional()) {
                $schema['default'] = $param->getDefault();
            } else {
                $required[] = $param->name;
            }
            $properties[$param->name] = $schema;
        }

        $inputSchema = ['type' => 'object', 'properties' => $properties, 'additionalProperties' => false];
        if ($required !== []) {
            $inputSchema['required'] = $required;
        }
        // Each MCP tool must carry its own definitions; OpenRPC component refs
        // cannot be resolved by an MCP client (including recursive DTO schemas).
        if ($this->schemas !== []) {
            $inputSchema['$defs'] = $this->schemas;
        }

        return $this->rewriteRefs($inputSchema);
    }

    /**
     * The result schema the core OpenRpcAdapter publishes for the method, wrapped the way
     * RpcToolExecutor returns it (structuredContent: {"result": ...}). No schema when the
     * method declares no return type.
     *
     * A PHP array is serialized as a JSON list or a JSON object depending on its keys, so
     * every "array" node accepts an object too — otherwise clients that validate
     * structuredContent against outputSchema would reject e.g. ['a' => 1].
     *
     * No schema either for a bare "any object" (no return type, or "object"): the core describes
     * a method without a return type that way, so it would reject the scalar such a method may return,
     * and it says nothing about the structure anyway.
     */
    private function buildOutputSchema(Service $service): ?array
    {
        if (empty($service->getReturn()) || $service->getReturn() === self::ANY_OBJECT) {
            return null;
        }

        $this->schemas = [];
        $resultSchema = T::applyToSchema(
            $service->getReturn(),
            fn(array $schema) => $this->checkAndGetSchemaFromDesc($schema)
        );
        $outputSchema = [
            'type' => 'object',
            'properties' => ['result' => $resultSchema],
            'required' => ['result'],
        ];
        if ($this->schemas !== []) {
            $outputSchema['$defs'] = $this->schemas;
        }

        return $this->allowObjectForArrays($this->rewriteRefs($outputSchema));
    }

    private function allowObjectForArrays(array $schema): array
    {
        foreach ($schema as $key => $value) {
            if ($key === 'type' && $value === 'array') {
                $schema[$key] = ['array', 'object'];
            } elseif (is_array($value)) {
                $schema[$key] = $this->allowObjectForArrays($value);
            }
        }
        return $schema;
    }

    private function buildAnnotations(Service $service): ?ToolAnnotations
    {
        $attribute = $service->getAttrCollection()->getAttribute(McpTool::class);
        if ($attribute === null) {
            return null;
        }

        return new ToolAnnotations(
            readOnlyHint: $attribute->readOnlyHint,
            destructiveHint: $attribute->destructiveHint,
            idempotentHint: $attribute->idempotentHint,
            openWorldHint: $attribute->openWorldHint,
        );
    }

    private function rewriteRefs(array $schema): array
    {
        foreach ($schema as $key => $value) {
            if ($key === '$ref' && is_string($value) && str_starts_with($value, self::SCHEMAS)) {
                $schema[$key] = '#/$defs/'.substr($value, strlen(self::SCHEMAS));
            } elseif (is_array($value)) {
                $schema[$key] = $this->rewriteRefs($value);
            }
        }
        return $schema;
    }

    protected function getParamConvertor(): ChainParamConvertor
    {
        return $this->paramConvertor;
    }

    protected function getEnumsHolder(): EnumsHolder
    {
        return $this->enumsHolder;
    }
}
