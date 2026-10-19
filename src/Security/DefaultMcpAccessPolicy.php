<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Security;

/**
 * Restricts nothing by default: no DNS-rebinding host check, CORS answers any origin.
 * The core token auth (IRpcSecurity, checked before this policy ever runs —
 * see Ufo\McpBundle\EventListener\McpRequestListener) is the actual gate; this policy only
 * controls two defense-in-depth HTTP layers on top of it. Override this service (DI
 * decorator/alias) with a real hostname/origin allowlist for a deployment that needs
 * DNS-rebinding protection or a locked-down CORS policy — this default does not restrict.
 */
final class DefaultMcpAccessPolicy implements McpAccessPolicyInterface
{
    public function getAllowedHosts(): array
    {
        return [];
    }

    public function getAllowedOrigins(): array
    {
        return ['*'];
    }
}
