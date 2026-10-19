<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Server\RequestPrepare\Holders;

use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcRequestCarrier;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;

/** One MCP tools/call as an RPC request: the tool's arguments become the method's params. */
final readonly class RpcFromMcp implements IRpcRequestCarrier
{
    private RpcRequest $request;

    /** $version must be the same RPC service-map version the tool's schema was built from (Ufo\McpBundle\Server\McpServerFactory).
     * @throws AbstractRpcErrorException
     */
    public function __construct(Service $service, array $arguments, string $version)
    {
        $this->request = new RpcRequest(RpcRequest::generateId(), $service->name, $arguments, apiVersion: $version);
    }

    public function getRequestObject(): RpcRequest
    {
        return $this->request;
    }

    public function getBatchRequestObject(): RpcBatchRequest
    {
        throw new WrongWayException();
    }
}
