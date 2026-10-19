<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Unit\ConfigService;

use PHPUnit\Framework\TestCase;
use Ufo\McpBundle\ConfigService\McpServerInfoConfig;

final class McpServerInfoConfigTest extends TestCase
{
    public function testFromConfigMapsIconsToRegistryFieldNames(): void
    {
        $info = McpServerInfoConfig::fromConfig([
            'name' => 'io.test/app',
            'icons' => [['src' => 'https://cdn.test/i.png', 'mime_type' => 'image/png', 'sizes' => ['32x32'], 'theme' => 'light'], ['src' => 'https://cdn.test/i.svg']],
        ]);

        self::assertSame('io.test/app', $info->name);
        self::assertNull($info->title);
        self::assertSame([
            ['src' => 'https://cdn.test/i.png', 'mimeType' => 'image/png', 'sizes' => ['32x32'], 'theme' => 'light'],
            ['src' => 'https://cdn.test/i.svg', 'mimeType' => null, 'sizes' => [], 'theme' => null],
        ], $info->icons);
    }

    public function testFromEmptyConfig(): void
    {
        self::assertEquals(new McpServerInfoConfig(), McpServerInfoConfig::fromConfig([]));
    }

    public function testOverridingKeepsSetFieldsAndTakesTheRestFromBase(): void
    {
        $base = new McpServerInfoConfig('io.test/app', 'App', 'Base', [['src' => 'base.png']]);

        $merged = (new McpServerInfoConfig(title: 'Internal'))->overriding($base);
        self::assertEquals(new McpServerInfoConfig('io.test/app', 'Internal', 'Base', [['src' => 'base.png']]), $merged);

        $ownIcons = (new McpServerInfoConfig(icons: [['src' => 'own.png']]))->overriding($base);
        self::assertSame([['src' => 'own.png']], $ownIcons->icons);
    }
}
