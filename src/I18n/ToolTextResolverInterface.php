<?php

declare(strict_types=1);

namespace Ufo\McpBundle\I18n;

/**
 * Resolves a tool's display title/description, optionally translated. The RPC method
 * name is the lookup key (see DefaultToolTextResolver/TranslatedToolTextResolver and
 * docs/ for the translation file format). $fallback is always the already-known
 * value to use when nothing better is found — the tool's own name for title, its
 * RPC docblock for description — never empty.
 */
interface ToolTextResolverInterface
{
    public function title(string $toolName, string $fallback): string;

    public function description(string $toolName, string $fallback): string;
}
