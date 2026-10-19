<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Fixtures\Procedures;

use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

#[RPC\Info('Catalog', version: 2)]
#[RPC\APIGroups(['mcp'], false)]
class CatalogV2Procedure implements IRpcService
{
    /** Ping, version 2. */
    public function ping(): string
    {
        return 'pong v2';
    }
}
