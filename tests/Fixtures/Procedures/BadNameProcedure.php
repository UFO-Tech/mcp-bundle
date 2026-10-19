<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Fixtures\Procedures;

use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

/** "Bad:ping" — the ":" is not allowed in an MCP tool name. */
#[RPC\Info('Bad', concat: ':')]
#[RPC\APIGroups(['bad'], false)]
class BadNameProcedure implements IRpcService
{
    /** Ping. */
    public function ping(): string
    {
        return 'pong';
    }
}
