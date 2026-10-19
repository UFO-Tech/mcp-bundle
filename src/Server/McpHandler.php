<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Server;

use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Ufo\JsonRpcBundle\Server\RpcLocaleNegotiator;
use Ufo\McpBundle\Server\Transport\StreamableHttpTransportFactory;

/**
 * Owns the MCP-SDK/PSR-7 plumbing for one request, so the controller only delegates
 * here (group, availability and token checks run before it — see
 * Ufo\McpBundle\EventListener\McpRequestListener). The language of
 * tool titles/descriptions is negotiated here the same way the core documentation does
 * it (RpcLocaleNegotiator) and reported back with Content-Language / Vary.
 */
final readonly class McpHandler
{
    public function __construct(
        private StreamableHttpTransportFactory $transportFactory,
        private HttpFoundationFactory $httpFoundationFactory,
        private McpServerFactory $serverFactory,
        private RpcLocaleNegotiator $localeNegotiator,
    ) {}

    /** $version is the RPC service-map version resolved from the request's "{ver}" route segment (Ufo\McpBundle\Controller\McpController), threaded through to McpServerFactory. */
    public function handle(Request $request, string $version): Response
    {
        $locale = $this->localeNegotiator->negotiate($request);
        if ($locale !== null) {
            $request->setLocale($locale);
        }
        $transport = $this->transportFactory->create($request);

        $response = $this->httpFoundationFactory->createResponse(
            $this->serverFactory
                ->create($version, $locale)
                ->run($transport)
        );
        $this->localeNegotiator->addLanguageHeaders($request, $response);

        return $response;
    }
}
