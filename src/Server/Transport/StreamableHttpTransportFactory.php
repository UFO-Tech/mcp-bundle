<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Server\Transport;

use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StatelessAwareTransportInterface;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\McpBundle\ConfigService\McpConfig;
use Ufo\McpBundle\Security\McpAccessPolicyInterface;

/**
 * Builds the SDK StreamableHttpTransport for the current request: CORS (allowing the
 * MCP headers and the core RPC token header) and, when McpAccessPolicyInterface lists
 * hosts, DNS-rebinding protection. The transport itself serves both the session-based
 * protocol and the stateless 2026-07-28 one.
 */
readonly class StreamableHttpTransportFactory
{
    public function __construct(
        private Psr17Factory $psr17,
        private PsrHttpFactory $psrHttpFactory,
        private McpConfig $config,
        private RpcMainConfig $rpcConfig,
        private ApiGroupContext $apiGroupContext,
        private McpAccessPolicyInterface $accessPolicy,
    ) {}

    public function create(
        Request $request
    ): StatelessAwareTransportInterface
    {
        $maxBodyBytes = $this->config->group($this->apiGroupContext->getGroup())->maxBodyBytes;
        $psrRequest = $this->psrHttpFactory->createRequest($request);
        $middleware = [
            new CorsMiddleware(
                allowedOrigins: $this->accessPolicy->getAllowedOrigins(),
                allowedHeaders: [
                    'Accept', 'Authorization', 'Content-Type', 'Last-Event-ID',
                    'Mcp-Session-Id', 'Mcp-Protocol-Version', 'Mcp-Method', 'Mcp-Name',
                    $this->rpcConfig->securityConfig->tokenName,
                ],
            ),
        ];

        if (!empty($this->accessPolicy->getAllowedHosts())) {
            $middleware[] = new DnsRebindingProtectionMiddleware($this->accessPolicy->getAllowedHosts(), $this->psr17, $this->psr17);
        }
        return new StreamableHttpTransport(
            $psrRequest,
            $this->psr17,
            $this->psr17,
            middleware: $middleware,
            maxBodyBytes: $maxBodyBytes,
        );
    }
}