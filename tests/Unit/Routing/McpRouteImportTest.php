<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Ufo\McpBundle\Routing\McpRouteImport;

final class McpRouteImportTest extends TestCase
{
    public function testRegistersTheBundleRouter(): void
    {
        $import = new McpRouteImport();

        self::assertSame(realpath(__DIR__.'/../../../config/router.yaml'), realpath($import->getResource()));
        self::assertSame('_mcp_name_prefix', $import->getRouteNamePrefixAttribute());
    }
}
