<?php

declare(strict_types=1);

namespace Ufo\McpBundle\ClientManifest;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * Named-strategy holder, the same shape as the core bundle's own
 * Ufo\JsonRpcBundle\Security\TokenValidatorRegistry: a ServiceLocator keyed by a
 * string, built from a DI tag instead of a hand-written compiler pass.
 */
final class McpClientManifestGeneratorHolder
{
    public const string TAG = 'ufo_mcp.client_manifest_generator';

    public function __construct(
        #[AutowireLocator(self::TAG, indexAttribute: 'key')]
        private readonly ContainerInterface $generators,
    ) {}

    public function get(string $target): ?McpClientManifestGeneratorInterface
    {
        return $this->generators->has($target) ? $this->generators->get($target) : null;
    }
}
