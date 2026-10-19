<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Ufo\McpBundle\Availability\DefaultMcpAvailabilityChecker;
use Ufo\McpBundle\ConfigService\McpConfig;
use Ufo\McpBundle\DependencyInjection\Configuration;
use Ufo\McpBundle\Security\DefaultMcpAccessPolicy;

/** The default implementations of the two extension points. */
final class DefaultsTest extends TestCase
{
    public function testAccessPolicyRestrictsNothing(): void
    {
        $policy = new DefaultMcpAccessPolicy();

        self::assertSame([], $policy->getAllowedHosts());
        self::assertSame(['*'], $policy->getAllowedOrigins());
    }

    public function testAvailabilityFollowsTheConfig(): void
    {
        $config = new McpConfig((new Processor())->processConfiguration(new Configuration(), [[
            'enabled' => true,
            'groups' => ['off' => ['enabled' => false]],
        ]]));
        $checker = new DefaultMcpAvailabilityChecker($config);

        self::assertTrue($checker->isEnabled('mcp'));
        self::assertTrue($checker->isEnabled('not-configured'));
        self::assertFalse($checker->isEnabled('off'));
    }
}
