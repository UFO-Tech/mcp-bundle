<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Services;

use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Ufo\JsonRpcBundle\DocAdapters\Traits\JsonSchemaDtoFormatTrait;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\EnumProcessor\EnumsHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\McpBundle\Attribute\McpTool;
use Ufo\McpBundle\I18n\ToolTextResolverInterface;

final class ToolSchemaFactory
{
    use JsonSchemaDtoFormatTrait;

    public function __construct(
        private readonly ChainParamConvertor $paramConvertor,
        private readonly EnumsHolder $enumsHolder,
        private readonly ToolTextResolverInterface $textResolver,
    ) {}

    public function create(string $name, Service $service): Tool
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
        $inputSchema = $this->rewriteRefs($inputSchema);
        $attribute = $service->getAttrCollection()->getAttribute(McpTool::class);

        return new Tool(
            name: $name,
            title: $this->textResolver->title($name, $name),
            inputSchema: $inputSchema,
            description: $this->textResolver->description($name, $service->getDescription()),
            annotations: $attribute === null ? null : new ToolAnnotations(
                readOnlyHint: $attribute->readOnlyHint,
                destructiveHint: $attribute->destructiveHint,
                idempotentHint: $attribute->idempotentHint,
                openWorldHint: $attribute->openWorldHint,
            ),
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
