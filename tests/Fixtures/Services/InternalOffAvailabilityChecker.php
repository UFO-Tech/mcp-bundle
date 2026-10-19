<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Fixtures\Services;

use Ufo\McpBundle\Availability\McpAvailabilityCheckerInterface;

/** A feature-flag style checker: the "internal" group is switched off whatever the config says. */
final class InternalOffAvailabilityChecker implements McpAvailabilityCheckerInterface
{
    public function isEnabled(string $group): bool
    {
        return $group !== 'internal';
    }
}
