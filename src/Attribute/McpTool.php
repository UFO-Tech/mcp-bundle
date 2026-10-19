<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Attribute;

use Attribute;

/**
 * MCP ToolAnnotations hints for an RPC method. It does not decide exposure: every RPC
 * method of the API group an MCP endpoint serves is a tool, named after the RPC method.
 * Title/description come from the core RPC translations (translations/rpc.<locale>.yaml,
 * per group translations/rpc/<group>.<locale>.yaml), falling back to #[RPC\Info]/PHPDoc.
 * Each hint is a tri-state (true/false/null
 * "unspecified") per the MCP ToolAnnotations spec, which is why these stay plain
 * nullable booleans rather than a bitmask: a bitmask can't tell "false" apart from
 * "not specified" without doubling its bits.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class McpTool
{
    public function __construct(
        /** If true, the tool does not modify its environment. */
        public ?bool $readOnlyHint = null,
        /** If true, the tool may perform destructive updates; if false, only additive ones. */
        public ?bool $destructiveHint = null,
        /** If true, calling the tool again with the same arguments has no further effect (meaningful only when readOnlyHint is false). */
        public ?bool $idempotentHint = null,
        /** If true, the tool interacts with an "open world" of external entities (e.g. the web); if false, its domain is closed. */
        public ?bool $openWorldHint = null,
    ) {}
}
