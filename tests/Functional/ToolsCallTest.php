<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\McpBundle\Tests\Fixtures\Procedures\CatalogProcedure;
use Ufo\McpBundle\Tests\Fixtures\TestKernel;
use Ufo\RpcError\RpcBadRequestException;

/**
 * tools/call goes through the core RpcExecutor: result normalization, errors, cache and RPC events.
 */
final class ToolsCallTest extends McpKernelTestCase
{
    /** @var array<string, int> RPC event name => times fired */
    private array $fired = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->fired = [];
        $dispatcher = self::getContainer()->get('event_dispatcher');
        foreach ([RpcEvent::POST_RESPONSE, RpcEvent::ERROR] as $event) {
            $dispatcher->addListener($event, function () use ($event): void {
                $this->fired[$event] = ($this->fired[$event] ?? 0) + 1;
            });
        }
    }

    private function fired(string $event): int
    {
        return $this->fired[$event] ?? 0;
    }

    public function testScalarResult(): void
    {
        $result = $this->callTool($this->initialize(), 'Catalog.ping', ['who' => 'mcp']);

        self::assertSame([
            'content' => [['type' => 'text', 'text' => '"pong mcp"']],
            'isError' => false,
            'structuredContent' => ['result' => 'pong mcp'],
        ], $result);
    }

    public function testDefaultArgument(): void
    {
        self::assertSame(['result' => 'pong x'], $this->callTool($this->initialize(), 'Catalog.ping')['structuredContent']);
    }

    public function testAssociativeArrayIsAJsonObject(): void
    {
        $result = $this->callTool($this->initialize(), 'Catalog.stats');

        self::assertSame('{"a":1}', $result['content'][0]['text']);
        self::assertSame(['result' => ['a' => 1]], $result['structuredContent']);
    }

    public function testDtoResultIsNormalizedByTheCore(): void
    {
        $result = $this->callTool($this->initialize(), 'Catalog.item', ['id' => 5]);

        self::assertSame(['result' => ['id' => 5, 'name' => 'n5', 'color' => 'red']], $result['structuredContent']);
        self::assertSame('{"id":5,"name":"n5","color":"red"}', $result['content'][0]['text']);
    }

    public function testEnumArgument(): void
    {
        $sessionId = $this->initialize();

        self::assertSame(
            ['result' => [['id' => 1, 'name' => 'n1', 'color' => 'blue']]],
            $this->callTool($sessionId, 'Catalog.byColor', ['color' => 'blue'])['structuredContent'],
        );
        self::assertSame(
            ['result' => [['id' => 1, 'name' => 'n1', 'color' => 'red']]],
            $this->callTool($sessionId, 'Catalog.byColor')['structuredContent'],
            'the enum default',
        );

        $invalid = $this->call($sessionId, 'tools/call', ['name' => 'Catalog.byColor', 'arguments' => ['color' => 'green']]);
        self::assertSame(-32602, $invalid['error']['code'], 'rejected by inputSchema before the core');
    }

    public function testMethodWithoutReturnType(): void
    {
        self::assertSame(['result' => 'untyped'], $this->callTool($this->initialize(), 'Catalog.untyped')['structuredContent']);
    }

    public function testVersionedImplementation(): void
    {
        self::assertSame(['result' => 'pong v2'], $this->callTool($this->initialize('/mcp/v2'), 'Catalog.ping', path: '/mcp/v2')['structuredContent']);
    }

    public function testErrorInsideTheMethod(): void
    {
        $result = $this->callTool($this->initialize(), 'Catalog.boom');

        self::assertTrue($result['isError']);
        self::assertArrayNotHasKey('structuredContent', $result);
        $error = json_decode($result['content'][0]['text'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(-32603, $error['code']);
        self::assertSame('boom inside', $error['message']);
        self::assertSame(1, $this->fired(RpcEvent::ERROR));
        self::assertSame(0, $this->fired(RpcEvent::POST_RESPONSE), 'no rpc.post_response for an error response');
    }

    public function testExceptionEscapingTheHandlerGoesThroughRpcError(): void
    {
        self::getContainer()->get('event_dispatcher')->addListener(RpcEvent::REQUEST, static function (): void {
            throw new RpcBadRequestException('rejected by a listener');
        });

        $result = $this->callTool($this->initialize(), 'Catalog.ping');

        self::assertTrue($result['isError']);
        $error = json_decode($result['content'][0]['text'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([-32600, 'rejected by a listener'], [$error['code'], $error['message']]);
        self::assertSame(1, $this->fired(RpcEvent::ERROR));
    }

    /**
     * Separate process: compiling another container in a process where a DTO was already normalized breaks
     * the next DTO normalization (core RpcServiceMapPass::compileSerializer() prepends normalizers to the
     * process-wide Transformer::getDefault(), the Serializer's normalizer cache keeps the old indexes).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testErrorDataFollowsTheEnvironment(): void
    {
        self::ensureKernelShutdown();
        self::bootKernel(['environment' => 'prod']);

        $result = $this->callTool($this->initialize(), 'Catalog.boom');
        $error = json_decode($result['content'][0]['text'], true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(['exception' => 'Ufo\RpcError\RpcInternalException'], $error['data'], 'no file, line or trace in prod');
    }

    public function testArgumentsAreValidatedAgainstInputSchema(): void
    {
        $sessionId = $this->initialize();

        $extra = $this->call($sessionId, 'tools/call', ['name' => 'Catalog.ping', 'arguments' => ['who' => 'a', '$rpc' => ['callback' => 'http://127.0.0.1:9/cb']]]);
        self::assertSame(-32602, $extra['error']['code']);
        self::assertStringContainsString('$rpc', $extra['error']['message']);

        $missing = $this->call($sessionId, 'tools/call', ['name' => 'Catalog.item', 'arguments' => new \stdClass()]);
        self::assertSame(-32602, $missing['error']['code']);

        $wrongType = $this->call($sessionId, 'tools/call', ['name' => 'Catalog.item', 'arguments' => ['id' => 'five']]);
        self::assertSame(-32602, $wrongType['error']['code']);
    }

    public function testUnknownTool(): void
    {
        $response = $this->call($this->initialize(), 'tools/call', ['name' => 'Nope.missing', 'arguments' => new \stdClass()]);

        self::assertSame(-32602, $response['error']['code']);
    }

    public function testToolOfAnotherGroupIsNotCallable(): void
    {
        $response = $this->call($this->initialize('/mcp'), 'tools/call', ['name' => 'Internal.secret', 'arguments' => new \stdClass()]);
        self::assertSame(-32602, $response['error']['code']);

        self::assertSame(['result' => 'secret'], $this->callTool($this->initialize('/mcp/internal'), 'Internal.secret', path: '/mcp/internal')['structuredContent']);
    }

    public function testPostResponseFiresAfterTheResponseIsSent(): void
    {
        $sessionId = $this->initialize();
        $request = Request::create('http://localhost/mcp', 'POST', server: [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_UFO_RPC_TOKEN' => TestKernel::TOKEN, 'HTTP_MCP_SESSION_ID' => $sessionId,
        ], content: json_encode(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'Catalog.ping', 'arguments' => ['who' => 'z']]]));

        $response = self::$kernel->handle($request);
        self::assertSame(0, $this->fired(RpcEvent::POST_RESPONSE), 'not before kernel.terminate');

        self::$kernel->terminate($request, $response);
        self::assertSame(1, $this->fired(RpcEvent::POST_RESPONSE));
    }

    public function testBatchOfToolCallsFiresPostResponseForEach(): void
    {
        $sessionId = $this->initialize();
        $response = $this->post('/mcp', [
            ['jsonrpc' => '2.0', 'id' => 21, 'method' => 'tools/call', 'params' => ['name' => 'Catalog.ping', 'arguments' => ['who' => 'a']]],
            ['jsonrpc' => '2.0', 'id' => 22, 'method' => 'tools/call', 'params' => ['name' => 'Catalog.ping', 'arguments' => ['who' => 'b']]],
        ], ['Mcp-Session-Id' => $sessionId]);

        $results = array_column($this->json($response), 'result', 'id');
        self::assertSame(['result' => 'pong a'], $results[21]['structuredContent']);
        self::assertSame(['result' => 'pong b'], $results[22]['structuredContent']);
        self::assertSame(2, $this->fired(RpcEvent::POST_RESPONSE));
    }

    public function testErrorDoesNotLeakIntoTheNextCallOfTheBatch(): void
    {
        $sessionId = $this->initialize();
        $response = $this->post('/mcp', [
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'Catalog.item', 'arguments' => ['id' => 5]]],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'Catalog.boom', 'arguments' => new \stdClass()]],
            ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'Catalog.item', 'arguments' => ['id' => 6]]],
            ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'Catalog.ping', 'arguments' => new \stdClass()]],
        ], ['Mcp-Session-Id' => $sessionId]);

        $results = array_column($this->json($response), 'result', 'id');
        self::assertSame(['result' => ['id' => 5, 'name' => 'n5', 'color' => 'red']], $results[1]['structuredContent']);
        self::assertTrue($results[2]['isError']);
        self::assertArrayNotHasKey('result', json_decode($results[2]['content'][0]['text'], true, flags: JSON_THROW_ON_ERROR));
        // Before core 10.4.3 / rpc-objects 3.9.2 the DTO after an error came back as [].
        self::assertSame(['result' => ['id' => 6, 'name' => 'n6', 'color' => 'red']], $results[3]['structuredContent']);
        self::assertSame(['result' => 'pong x'], $results[4]['structuredContent']);
    }

    public function testCachedResponse(): void
    {
        CatalogProcedure::$cachedExecutions = 0;
        self::getContainer()->get('rpc.cache.pool')->clear();
        $sessionId = $this->initialize();

        self::assertSame(['result' => 'fresh'], $this->callTool($sessionId, 'Catalog.cached')['structuredContent']);
        self::assertSame(['result' => 'fresh'], $this->callTool($sessionId, 'Catalog.cached')['structuredContent']);

        self::assertSame(1, CatalogProcedure::$cachedExecutions, 'the second call is served by the core response cache');
        self::assertSame(1, $this->fired(RpcEvent::POST_RESPONSE), 'none for a cached response');
    }
}
