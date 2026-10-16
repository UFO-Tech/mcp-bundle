<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Services;

use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\McpBundle\ConfigService\McpConfig;
use Ufo\McpBundle\Security\McpAccessPolicyInterface;

/**
 * Owns every bit of MCP-SDK/PSR-7 plumbing the controller used to build inline with
 * `new` — the controller stays a thin auth gate that delegates here.
 */
final readonly class McpTransportFactory
{
    public function __construct(
        private Psr17Factory $psr17,
        private PsrHttpFactory $psrHttpFactory,
        private HttpFoundationFactory $httpFoundationFactory,
        private McpServerFactory $serverFactory,
        private McpAccessPolicyInterface $accessPolicy,
        private McpConfig $config,
        private ApiGroupContext $apiGroupContext,
        private RpcMainConfig $rpcConfig,
    ) {}

    /** $version is the RPC service-map version resolved from the request's "{ver}" route segment (Ufo\McpBundle\Controller\McpController), threaded through to McpServerFactory. */
    public function handle(Request $request, string $version): Response
    {
        $maxBodyBytes = $this->config->group($this->apiGroupContext->getGroup())->maxBodyBytes;
        $allowedHosts = $this->accessPolicy->getAllowedHosts();

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
        // DnsRebindingProtectionMiddleware's own allowlist has no wildcard: passing it an
        // empty list would reject every request with a Host/Origin header, the opposite
        // of "no restriction" — so an empty list omits the middleware entirely instead.
        if ($allowedHosts !== []) {
            $middleware[] = new DnsRebindingProtectionMiddleware($allowedHosts, $this->psr17, $this->psr17);
        }

        $psrRequest = $this->psrHttpFactory->createRequest($request);
        $transport = new StreamableHttpTransport(
            $psrRequest, $this->psr17, $this->psr17,
            middleware: $middleware,
            maxBodyBytes: $maxBodyBytes,
        );

        return $this->httpFoundationFactory->createResponse($this->serverFactory->create($version)->run($transport));
    }
}
