<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Services;

use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcRequestCarrier;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;

final readonly class RpcFromMcp implements IRpcRequestCarrier
{
    public function __construct(private RpcRequest $request) {}

    public function getRequestObject(): RpcRequest
    {
        return $this->request;
    }

    public function getBatchRequestObject(): RpcBatchRequest
    {
        throw new WrongWayException();
    }
}
