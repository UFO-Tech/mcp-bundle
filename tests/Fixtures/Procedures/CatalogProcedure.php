<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Fixtures\Procedures;

use RuntimeException;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\McpBundle\Attribute\McpTool;
use Ufo\McpBundle\Tests\Fixtures\Dto\Color;
use Ufo\McpBundle\Tests\Fixtures\Dto\ItemDto;
use Ufo\RpcObject\RPC;

#[RPC\Info('Catalog')]
#[RPC\APIGroups(['mcp', 'internal'], false)]
class CatalogProcedure implements IRpcService
{
    public static int $cachedExecutions = 0;

    /**
     * Ping from PHPDoc.
     * @param string $who Who is pinging
     */
    #[McpTool(readOnlyHint: true, idempotentHint: true)]
    public function ping(string $who = 'x'): string
    {
        return 'pong '.$who;
    }

    /** Associative array result. */
    public function stats(): array
    {
        return ['a' => 1];
    }

    /** One item as a DTO. */
    public function item(int $id): ItemDto
    {
        return new ItemDto($id, 'n'.$id);
    }

    /**
     * Items of a color.
     * @return ItemDto[]
     */
    public function byColor(Color $color = Color::Red): array
    {
        return [new ItemDto(1, 'n1', $color)];
    }

    /** Throws inside the method. */
    public function boom(): string
    {
        throw new RuntimeException('boom inside');
    }

    /** Cached response. */
    #[RPC\Cache(environments: ['test'])]
    public function cached(): string
    {
        ++self::$cachedExecutions;
        return 'fresh';
    }

    /** No declared return type. */
    public function untyped()
    {
        return 'untyped';
    }
}
