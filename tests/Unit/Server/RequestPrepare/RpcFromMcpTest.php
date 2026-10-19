<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Unit\Server\RequestPrepare;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\McpBundle\Server\RequestPrepare\Holders\RpcFromMcp;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Info;

final class RpcFromMcpTest extends TestCase
{
    public function testToolCallBecomesAnRpcRequest(): void
    {
        $service = new Service('Catalog.ping', 'App\Procedure', new Info('Catalog'));

        $request = (new RpcFromMcp($service, ['who' => 'mcp'], 'v2'))->getRequestObject();

        self::assertSame('Catalog.ping', $request->getMethod());
        self::assertSame(['who' => 'mcp'], $request->getParams());
        self::assertSame('v2', $request->apiVersion);
        self::assertSame('2.0', $request->getVersion());
        self::assertNotSame('', (string) $request->getId());
    }

    public function testEveryCallGetsItsOwnId(): void
    {
        $service = new Service('Catalog.ping', 'App\Procedure', new Info('Catalog'));

        self::assertNotSame(
            (new RpcFromMcp($service, [], 'v1'))->getRequestObject()->getId(),
            (new RpcFromMcp($service, [], 'v1'))->getRequestObject()->getId(),
        );
    }

    public function testNeverABatch(): void
    {
        $this->expectException(WrongWayException::class);
        (new RpcFromMcp(new Service('Catalog.ping', 'App\Procedure', new Info('Catalog')), [], 'v1'))->getBatchRequestObject();
    }
}
