<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Server\Session;

use Mcp\Server\Session\SessionStoreInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Legacy MCP session storage on top of the json-rpc-bundle cache pool (PSR-6) — the same
 * behaviour as the SDK's own Psr16SessionStore, without requiring psr/simple-cache.
 * Expiry is per item (the session TTL), so the pool's own default lifetime doesn't apply,
 * and gc() has nothing to do.
 */
final readonly class RpcCacheSessionStore implements SessionStoreInterface
{
    public function __construct(
        private CacheItemPoolInterface $pool,
        private string $prefix,
        private int $ttl,
    ) {}

    public function exists(Uuid $id): bool
    {
        try {
            return $this->pool->hasItem($this->key($id));
        } catch (Throwable) {
            return false;
        }
    }

    public function read(Uuid $id): string|false
    {
        try {
            $item = $this->pool->getItem($this->key($id));
            return $item->isHit() ? $item->get() : false;
        } catch (Throwable) {
            return false;
        }
    }

    public function write(Uuid $id, string $data): bool
    {
        try {
            $item = $this->pool->getItem($this->key($id));
            return $this->pool->save($item->set($data)->expiresAfter($this->ttl));
        } catch (Throwable) {
            return false;
        }
    }

    public function destroy(Uuid $id): bool
    {
        try {
            return $this->pool->deleteItem($this->key($id));
        } catch (Throwable) {
            return false;
        }
    }

    public function gc(): array
    {
        return [];
    }

    private function key(Uuid $id): string
    {
        return $this->prefix.$id->toRfc4122();
    }
}
