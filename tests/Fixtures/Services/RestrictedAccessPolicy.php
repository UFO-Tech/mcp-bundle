<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Fixtures\Services;

use Ufo\McpBundle\Security\McpAccessPolicyInterface;

/**
 * What an app puts in place of DefaultMcpAccessPolicy: the server host plus the browser app's host
 * (the SDK checks the Origin's host against the same list) and that app as the only CORS origin.
 */
final class RestrictedAccessPolicy implements McpAccessPolicyInterface
{
    public function getAllowedHosts(): array
    {
        return ['mcp.allowed.test', 'app.allowed.test'];
    }

    public function getAllowedOrigins(): array
    {
        return ['https://app.allowed.test'];
    }
}
