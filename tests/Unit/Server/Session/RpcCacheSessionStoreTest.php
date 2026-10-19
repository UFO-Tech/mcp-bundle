<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Unit\Server\Session;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Uid\Uuid;
use Ufo\McpBundle\Server\Session\RpcCacheSessionStore;

final class RpcCacheSessionStoreTest extends TestCase
{
    public function testLifecycle(): void
    {
        $pool = new ArrayAdapter();
        $store = new RpcCacheSessionStore($pool, 'ufo_mcp_mcp_', 60);
        $id = Uuid::v4();

        self::assertFalse($store->exists($id));
        self::assertFalse($store->read($id));

        self::assertTrue($store->write($id, '{"a":1}'));
        self::assertTrue($store->exists($id));
        self::assertSame('{"a":1}', $store->read($id));
        self::assertTrue($pool->hasItem('ufo_mcp_mcp_'.$id->toRfc4122()), 'prefixed key in the pool');

        self::assertTrue($store->destroy($id));
        self::assertFalse($store->exists($id));
        self::assertSame([], $store->gc());
    }

    public function testPrefixesSeparateGroups(): void
    {
        $pool = new ArrayAdapter();
        $id = Uuid::v4();
        (new RpcCacheSessionStore($pool, 'ufo_mcp_a_', 60))->write($id, 'a');

        self::assertFalse((new RpcCacheSessionStore($pool, 'ufo_mcp_b_', 60))->exists($id));
    }

    public function testTtlIsSetOnEveryWrite(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects(self::once())->method('set')->with('data')->willReturnSelf();
        $item->expects(self::once())->method('expiresAfter')->with(42)->willReturnSelf();
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->expects(self::once())->method('save')->with($item)->willReturn(true);

        self::assertTrue((new RpcCacheSessionStore($pool, 'p_', 42))->write(Uuid::v4(), 'data'));
    }

    public function testPoolFailureMeansNoSession(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        foreach (['hasItem', 'getItem', 'deleteItem'] as $method) {
            $pool->method($method)->willThrowException(new RuntimeException('down'));
        }
        $store = new RpcCacheSessionStore($pool, 'p_', 60);
        $id = Uuid::v4();

        self::assertFalse($store->exists($id));
        self::assertFalse($store->read($id));
        self::assertFalse($store->write($id, 'x'));
        self::assertFalse($store->destroy($id));
    }
}
