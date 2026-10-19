<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\E2E;

use Mcp\Client;
use Mcp\Client\Transport\HttpTransport;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\ProtocolVersion;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\Process\Process;
use Ufo\McpBundle\Tests\Fixtures\TestKernel;

use function fsockopen;
use function stream_socket_get_name;
use function stream_socket_server;
use function usleep;

/**
 * The official MCP PHP SDK client against the fixture app served over real HTTP (PHP built-in server),
 * in both protocol eras: the session-based one and the stateless 2026-07-28 one.
 */
final class McpClientTest extends TestCase
{
    private static Process $server;
    private static string $baseUrl;

    public static function setUpBeforeClass(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        self::$baseUrl = 'http://'.$address;

        self::$server = new Process(
            [PHP_BINARY, '-S', $address, dirname(__DIR__).'/Fixtures/public/index.php'],
            dirname(__DIR__, 2),
            ['APP_ENV' => 'test'],
        );
        self::$server->start();

        [$host, $port] = explode(':', $address);
        for ($i = 0; $i < 100; ++$i) {
            if (@fsockopen($host, (int) $port)) {
                return;
            }
            usleep(50_000);
        }
        self::fail('The PHP built-in server did not start: '.self::$server->getErrorOutput());
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    private function client(string $path, ProtocolVersion $version, ?string $token = TestKernel::TOKEN): Client
    {
        $client = Client::builder()
            ->setClientInfo('phpunit-e2e', '1.0')
            ->setProtocolVersion($version)
            ->setInitTimeout(10)
            ->setRequestTimeout(10)
            ->setMaxRetries(0)
            ->build();
        $psr17 = new Psr17Factory();
        $client->connect(new HttpTransport(
            self::$baseUrl.$path,
            $token === null ? [] : ['Ufo-RPC-Token' => $token, 'Accept-Language' => 'uk'],
            new Psr18Client(HttpClient::create(), $psr17, $psr17),
            $psr17,
            $psr17,
        ));

        return $client;
    }

    public static function protocolVersions(): iterable
    {
        yield 'session-based 2025-11-25' => [ProtocolVersion::V2025_11_25];
        yield 'session-based 2025-06-18' => [ProtocolVersion::V2025_06_18];
        yield 'stateless 2026-07-28' => [ProtocolVersion::V2026_07_28];
    }

    #[DataProvider('protocolVersions')]
    public function testServerInfo(ProtocolVersion $version): void
    {
        $client = $this->client('/mcp/internal', $version);

        self::assertSame('io.test/internal', $client->getServerInfo()?->name);
        self::assertSame('v1', $client->getServerInfo()?->version);
        self::assertSame($version, $client->getProtocolVersion());
        $client->disconnect();
    }

    #[DataProvider('protocolVersions')]
    public function testListTools(ProtocolVersion $version): void
    {
        $client = $this->client('/mcp', $version);
        $tools = [];
        foreach ($client->listTools()->tools as $tool) {
            $tools[$tool->name] = $tool;
        }

        self::assertArrayHasKey('Catalog.ping', $tools);
        self::assertArrayNotHasKey('Internal.secret', $tools);
        self::assertSame('Пінг', $tools['Catalog.ping']->title);
        self::assertSame('Опис для MCP', $tools['Catalog.ping']->description);
        self::assertTrue($tools['Catalog.ping']->annotations?->readOnlyHint);
        self::assertSame(['result'], $tools['Catalog.item']->outputSchema['required'] ?? null);
        $client->disconnect();
    }

    #[DataProvider('protocolVersions')]
    public function testCallTool(ProtocolVersion $version): void
    {
        $client = $this->client('/mcp', $version);

        $result = $client->callTool('Catalog.item', ['id' => 7]);
        self::assertFalse($result->isError);
        self::assertSame(['result' => ['id' => 7, 'name' => 'n7', 'color' => 'red']], $result->structuredContent);
        self::assertInstanceOf(TextContent::class, $result->content[0]);

        $error = $client->callTool('Catalog.boom');
        self::assertTrue($error->isError);
        self::assertStringContainsString('boom inside', $error->content[0]->text);

        $client->disconnect();
    }

    public function testVersionedEndpoint(): void
    {
        $client = $this->client('/mcp/v2', ProtocolVersion::V2025_11_25);

        self::assertSame('v2', $client->getServerInfo()?->version);
        self::assertSame(['result' => 'pong v2'], $client->callTool('Catalog.ping')->structuredContent);
        $client->disconnect();
    }

    public function testWithoutTokenTheClientCannotConnect(): void
    {
        $this->expectException(\Throwable::class);
        $this->client('/mcp', ProtocolVersion::V2025_11_25, null);
    }
}
