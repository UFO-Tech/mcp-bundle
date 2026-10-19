<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Fixtures\Procedures;

use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

#[RPC\Info('Internal')]
#[RPC\APIGroups(['internal', 'off'], false)]
class InternalProcedure implements IRpcService
{
    /** Internal only. */
    public function secret(): string
    {
        return 'secret';
    }
}
