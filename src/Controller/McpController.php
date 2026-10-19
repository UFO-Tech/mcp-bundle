<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\McpBundle\ClientManifest\McpClientManifestGeneratorHolder;
use Ufo\McpBundle\ConfigService\McpConfig;
use Ufo\McpBundle\DocAdapters\Outputs\ServerJsonAdapter;
use Ufo\McpBundle\Server\McpHandler;
use Ufo\RpcObject\RPC\APIGroups;
use Ufo\RpcObject\RPC\Info;

use function sprintf;
use function str_ends_with;
use function strlen;
use function substr;

/**
 * API group, availability and token checks happen before any action runs — see
 * Ufo\McpBundle\EventListener\McpRequestListener.
 */
final class McpController
{
    /** Method name McpRequestListener uses to tell the MCP endpoint (API token) from the doc routes (doc token). */
    public const string ENDPOINT_ACTION = 'mcpAction';

    #[Route('/{ver<v\d+>}', name: 'ufo_mcp_endpoint', defaults: ['ver' => Info::DEFAULT_VERSION], methods: ['GET', 'POST', 'DELETE', 'OPTIONS'])]
    public function mcpAction(
        string $ver,
        Request $request,
        McpHandler $mcpHandler
    ): Response
    {
        return $mcpHandler->handle($request, $ver);
    }

    #[Route('/manifest/{aiName<[\w-]+>}.json', name: 'ufo_mcp_manifest', defaults: ['ver' => Info::DEFAULT_VERSION], methods: ['GET'])]
    #[Route('/{ver<v\d+>}/manifest/{aiName<[\w-]+>}.json', name: 'ufo_mcp_manifest_ver', methods: ['GET'])]
    public function manifestAction(
        string $aiName,
        Request $request,
        McpClientManifestGeneratorHolder $generators,
        McpConfig $config,
        RpcMainConfig $rpcConfig,
    ): Response
    {
        $generator = $generators->get($aiName);
        if ($generator === null) {
            return new JsonResponse(['error' => sprintf('Unknown MCP client manifest "%s".', $aiName)], 404);
        }

        return new JsonResponse($generator->generate(
            $config->group($request->attributes->get('api_group', APIGroups::DEFAULT_GROUP)),
            $this->endpointUrl($request, '/manifest/'.$aiName.'.json'),
            $rpcConfig->securityConfig->tokenName,
        ));
    }

    #[Route('/server.json', name: 'ufo_mcp_server_json', defaults: ['ver' => Info::DEFAULT_VERSION], methods: ['GET'])]
    #[Route('/{ver<v\d+>}/server.json', name: 'ufo_mcp_server_json_ver', methods: ['GET'])]
    public function serverJsonAction(
        Request $request,
        ServerJsonAdapter $serverJsonAdapter,
        McpConfig $config,
    ): Response
    {
        return new JsonResponse($serverJsonAdapter->adapt(
            $config->group($request->attributes->get('api_group', APIGroups::DEFAULT_GROUP)),
            $this->endpointUrl($request, '/server.json'),
            $request->attributes->get('ver', Info::DEFAULT_VERSION),
        ));
    }

    /** Absolute URL of the MCP endpoint this request was routed under: the request URL minus the given route suffix. */
    private function endpointUrl(Request $request, string $suffix): string
    {
        $path = $request->getPathInfo();
        if (str_ends_with($path, $suffix)) {
            $path = substr($path, 0, -strlen($suffix));
        }

        return $request->getSchemeAndHttpHost().$path;
    }
}
