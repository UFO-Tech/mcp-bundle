<?php

declare(strict_types=1);

namespace Ufo\McpBundle\I18n;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Looks up "<rpc method name>.title"/".description" in the "mcp_tools" translation
 * domain (a nested YAML file keyed by method name flattens to exactly this — see
 * docs/). Locale: the current request's Accept-Language (its first preference), or
 * the translator's own default locale when no request/header is available — no
 * further negotiation (Реакція: "зараз за це не заморачуємося").
 *
 * Only registered at all when the app has a "translator" service configured
 * (UfoMcpExtension) — never autowired directly, so an app without the translation
 * component never pays for or risks this class failing to construct.
 */
final class TranslatedToolTextResolver implements ToolTextResolverInterface
{
    private const string DOMAIN = 'mcp_tools';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
    ) {}

    public function title(string $toolName, string $fallback): string
    {
        return $this->resolve($toolName.'.title', $fallback);
    }

    public function description(string $toolName, string $fallback): string
    {
        return $this->resolve($toolName.'.description', $fallback);
    }

    private function resolve(string $id, string $fallback): string
    {
        $locale = $this->requestStack->getCurrentRequest()?->getPreferredLanguage() ?? $this->translator->getLocale();
        if (!$this->hasTranslation($id, $locale)) {
            return $fallback;
        }
        return $this->translator->trans($id, domain: self::DOMAIN, locale: $locale);
    }

    private function hasTranslation(string $id, string $locale): bool
    {
        // TranslatorBagInterface (getCatalogue) is how Symfony's own translator tells
        // "missing" apart from "translated to its own id" — trans() alone can't.
        if (!method_exists($this->translator, 'getCatalogue')) {
            return false;
        }
        return $this->translator->getCatalogue($locale)->has($id, self::DOMAIN);
    }
}
