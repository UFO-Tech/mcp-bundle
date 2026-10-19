<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tool;

use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Ufo\JsonRpcBundle\Server\RpcExecutor;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\McpBundle\Server\RequestPrepare\Holders\RpcFromMcp;
use Ufo\RpcObject\RpcResponse;

use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

/**
 * Runs one MCP tool call through the core RpcExecutor: errors that escape the handler go through
 * "rpc.error", and RpcEvent::POST_RESPONSE is deferred to kernel.terminate by the core
 * PostResponseQueue (once per call, also for several tools/call in one HTTP request).
 * Only the conversion of the RPC response into an MCP tool result is done here.
 */
readonly class RpcToolExecutor
{
    public function __construct(
        protected RpcExecutor $rpcExecutor,
        protected RpcRequestHandler $requestHandler,
    ) {}

    /** $version must be the same RPC service-map version the tool's schema was built from (Ufo\McpBundle\Server\McpServerFactory), not re-resolved here. */
    public function execute(Service $service, array $arguments, string $version): CallToolResult
    {
        return $this->toToolResult(
            $this->rpcExecutor->execute(
                new RpcFromMcp(
                    $service,
                    $arguments,
                    $version
                )
            )
        );
    }

    /**
     * The response is normalized once, the core way; the same value goes into the TextContent as JSON
     * (for clients that don't read structuredContent) and, on success, into structuredContent
     * (checked by clients against the tool's outputSchema).
     */
    private function toToolResult(RpcResponse $response): CallToolResult
    {
        $isError = $response->getError() !== null;
        $value = $this->requestHandler->responseToArray($response)[$isError ? 'error' : 'result'] ?? null;

        return new CallToolResult(
            [new TextContent(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))],
            isError: $isError,
            structuredContent: $isError ? null : ['result' => $value],
        );
    }
}
