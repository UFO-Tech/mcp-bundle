<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Security;

/**
 * Which hosts and browser origins the MCP transport accepts. Deciding this is the
 * consuming application's responsibility, not this bundle's config surface — override
 * this service (e.g. via a DI decorator or alias) when the defaults below don't fit.
 */
interface McpAccessPolicyInterface
{
    /**
     * @return string[] Hostnames accepted by DnsRebindingProtectionMiddleware. An empty
     *                   array means "no restriction" — Ufo\McpBundle\Services\McpTransportFactory
     *                   then omits that middleware entirely, since the middleware's own
     *                   allowlist check has no wildcard and would otherwise reject every
     *                   request with a Host/Origin header.
     */
    public function getAllowedHosts(): array;

    /** @return string[] Origins CorsMiddleware answers with permissive CORS headers. `['*']` allows any origin (the SDK's own wildcard support); `[]` disables the CORS header entirely. */
    public function getAllowedOrigins(): array;
}
