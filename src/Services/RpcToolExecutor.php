<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Services;

use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcObject\RpcRequest;

class RpcToolExecutor
{
    public function __construct(
        private readonly RpcRequestHandler $requestHandler,
        private readonly RequestCarrier $requestCarrier,
        private readonly RpcEventFactory $eventFactory,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /** $version must be the same RPC service-map version the tool's schema was built from (Ufo\McpBundle\Services\McpServerFactory), not re-resolved here. */
    public function execute(Service $service, array $arguments, string $version): CallToolResult
    {
        $this->eventFactory->reset();
        $this->requestCarrier->resetParams();
        $rpcRequest = null;
        try {
            $rpcRequest = new RpcRequest(
                RpcRequest::generateId(), $service->getName(), $arguments,
                apiVersion: $version,
            );
            $this->requestCarrier->setCarrier(new RpcFromMcp($rpcRequest));
            $response = $this->requestHandler->provideSingleRequest($rpcRequest);
            if (isset($response['error'])) {
                return CallToolResult::error([new TextContent(json_encode($response['error'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))]);
            }

            $result = $response['result'] ?? null;
            return new CallToolResult(
                [new TextContent(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))],
                structuredContent: ['result' => $result],
            );
        } catch (AbstractRpcErrorException $error) {
            return CallToolResult::error([new TextContent($error->getMessage())]);
        } catch (Throwable $error) {
            $this->logger->error('MCP RPC tool execution failed.', ['exception' => $error, 'method' => $service->getName()]);
            return CallToolResult::error([new TextContent('Internal error while executing RPC tool.')]);
        } finally {
            if ($rpcRequest?->getResponseObject() !== null) {
                $this->firePostResponse($rpcRequest, $service);
            }
        }
    }

    private function firePostResponse(RpcRequest $request, Service $service): void
    {
        try {
            $this->eventFactory->fire(RpcEvent::POST_RESPONSE, $request->getResponseObject(), $request, $service);
        } catch (Throwable $error) {
            $this->logger->error('MCP RPC post-response listener failed.', ['exception' => $error]);
        }
    }
}
