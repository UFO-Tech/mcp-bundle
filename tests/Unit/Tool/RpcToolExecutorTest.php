<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Unit\Tool;

use Mcp\Schema\Content\TextContent;
use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Server\RpcExecutor;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\McpBundle\Server\RequestPrepare\Holders\RpcFromMcp;
use Ufo\McpBundle\Tool\RpcToolExecutor;
use Ufo\McpBundle\Tool\RpcToolHandler;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\RpcResponse;

final class RpcToolExecutorTest extends TestCase
{
    private Service $service;

    protected function setUp(): void
    {
        $this->service = new Service('Catalog.item', 'App\Procedure', new Info('Catalog'));
    }

    private function executor(RpcResponse $response, array $normalized, ?callable $checkCarrier = null): RpcToolExecutor
    {
        $rpcExecutor = $this->createMock(RpcExecutor::class);
        $rpcExecutor->expects(self::once())->method('execute')
            ->with(self::callback($checkCarrier ?? static fn() => true))
            ->willReturn($response);
        $handler = $this->createMock(RpcRequestHandler::class);
        $handler->expects(self::once())->method('responseToArray')->with($response)->willReturn($normalized);

        return new RpcToolExecutor($rpcExecutor, $handler);
    }

    public function testCarrierCarriesTheToolCall(): void
    {
        $executor = $this->executor(new RpcResponse('1', 'x'), ['result' => 'x'], function (RpcFromMcp $carrier): bool {
            $request = $carrier->getRequestObject();
            return $request->getMethod() === 'Catalog.item' && $request->getParams() === ['id' => 5] && $request->apiVersion === 'v2';
        });

        $executor->execute($this->service, ['id' => 5], 'v2');
    }

    public function testSuccessIsTextAndStructuredContentOfTheNormalizedResult(): void
    {
        $result = $this->executor(new RpcResponse('1', ['id' => 5]), ['id' => '1', 'result' => ['id' => 5, 'name' => 'ї'], 'jsonrpc' => '2.0'])
            ->execute($this->service, [], 'v1');

        self::assertFalse($result->isError);
        self::assertSame(['result' => ['id' => 5, 'name' => 'ї']], $result->structuredContent);
        self::assertCount(1, $result->content);
        self::assertInstanceOf(TextContent::class, $result->content[0]);
        self::assertSame('{"id":5,"name":"ї"}', $result->content[0]->text);
    }

    public function testNullResult(): void
    {
        $result = $this->executor(new RpcResponse('1', null), ['id' => '1', 'result' => null])->execute($this->service, [], 'v1');

        self::assertSame(['result' => null], $result->structuredContent);
        self::assertSame('null', $result->content[0]->text);
    }

    public function testErrorIsTheNormalizedRpcErrorWithoutStructuredContent(): void
    {
        $error = ['code' => -32603, 'message' => 'boom', 'data' => ['exception' => 'X']];
        $result = $this->executor(new RpcResponse('1', error: new RpcError(-32603, 'boom', [])), ['id' => '1', 'error' => $error])
            ->execute($this->service, [], 'v1');

        self::assertTrue($result->isError);
        self::assertNull($result->structuredContent);
        self::assertSame(json_encode($error), $result->content[0]->text);
    }

    public function testHandlerPassesTheVersionOfItsTool(): void
    {
        $executor = $this->executor(new RpcResponse('1', 'x'), ['result' => 'x'], static fn(RpcFromMcp $carrier): bool => $carrier->getRequestObject()->apiVersion === 'v3');

        $result = (new RpcToolHandler($this->service, $executor, 'v3'))->execute(['id' => 1], new \Mcp\Server\ClientGateway($this->createMock(\Mcp\Server\Session\SessionInterface::class)));

        self::assertSame(['result' => 'x'], $result->structuredContent);
    }
}
