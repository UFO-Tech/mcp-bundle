<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tool;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ToolHandlerInterface;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;

final readonly class RpcToolHandler implements ToolHandlerInterface
{
    public function __construct(
        private Service $service,
        private RpcToolExecutor $executor,
        private string $version,
    ) {}

    public function execute(array $arguments, ClientGateway $gateway): CallToolResult
    {
        return $this->executor->execute($this->service, $arguments, $this->version);
    }
}
