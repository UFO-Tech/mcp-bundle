<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\TokenHolders\HttpTokenHolder;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\McpBundle\Availability\McpAvailabilityCheckerInterface;
use Ufo\McpBundle\ClientManifest\McpClientManifestGeneratorHolder;
use Ufo\McpBundle\ConfigService\McpConfig;
use Ufo\McpBundle\DocAdapters\Outputs\ServerJsonAdapter;
use Ufo\McpBundle\Services\McpTransportFactory;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcTokenNotSentException;
use Ufo\RpcObject\RPC\APIGroups;
use Ufo\RpcObject\RPC\Info;

use function sprintf;
use function str_ends_with;
use function strlen;
use function substr;

/**
 * Deliberately thin: auth gate + delegation. All SDK/PSR-7 construction lives in
 * McpTransportFactory; all "is this request allowed at all" logic lives in the core
 * bundle's own IRpcSecurity, keyed by whichever "api_group" the app's routing put on
 * the matched route's defaults (one entry mounted per group — see install/routes/).
 *
 * Every action also carries an optional "{ver<v\d+>}" segment, the same role it plays
 * in rest-bundle's RestEntryPointController/json-rpc-bundle's ApiController: a request
 * with no version segment serves Info::DEFAULT_VERSION ("v1"); "/v2" (etc.) serves that
 * RPC service-map version instead. A group's mount does not pin a single version — see
 * Ufo\McpBundle\ConfigService\McpGroupConfig.
 */
final class McpController
{
    /**
     * A single route: Symfony treats the slash before a trailing placeholder as
     * optional when that placeholder has a default, so this matches "/mcp" (ver=v1)
     * and "/mcp/v2" alike, for every method — including POST/DELETE, which (unlike
     * GET) never get a redirect from a mismatched trailing slash.
     */
    #[Route('/{ver<v\d+>}', name: 'ufo_mcp_endpoint', defaults: ['ver' => Info::DEFAULT_VERSION], methods: ['GET', 'POST', 'DELETE'])]
    public function mcpAction(
        Request $request,
        McpAvailabilityCheckerInterface $availabilityChecker,
        IRpcSecurity $rpcSecurity,
        RpcMainConfig $rpcConfig,
        ApiGroupContext $apiGroupContext,
        McpTransportFactory $transportFactory
    ): Response
    {
        $groupName = $request->attributes->get('api_group', APIGroups::DEFAULT_GROUP);
        if (!$availabilityChecker->isEnabled($groupName)) {
            return new Response('', 404);
        }

        $apiGroupContext->setGroup($groupName);
        try {
            $rpcSecurity->setTokenHolder(new HttpTokenHolder($rpcConfig, $request));
            try {
                $rpcSecurity->isValidApiRequest();
            } catch (RpcTokenNotSentException|RpcInvalidTokenException) {
                return new JsonResponse(['error' => 'Invalid or missing API token.'], 401);
            }

            return $transportFactory->handle($request, $request->attributes->get('ver', Info::DEFAULT_VERSION));
        } finally {
            $apiGroupContext->reset();
        }
    }

    /** CORS preflight never carries a token (per the CORS spec) — handled entirely by CorsMiddleware inside the shared transport, no auth gate here. Same single-route trick as mcpAction(). */
    #[Route('/{ver<v\d+>}', name: 'ufo_mcp_endpoint_options', defaults: ['ver' => Info::DEFAULT_VERSION], methods: ['OPTIONS'])]
    public function optionsAction(
        Request $request,
        McpAvailabilityCheckerInterface $availabilityChecker,
        ApiGroupContext $apiGroupContext,
        McpTransportFactory $transportFactory
    ): Response
    {
        $groupName = $request->attributes->get('api_group', APIGroups::DEFAULT_GROUP);
        if (!$availabilityChecker->isEnabled($groupName)) {
            return new Response('', 404);
        }

        $apiGroupContext->setGroup($groupName);
        try {
            return $transportFactory->handle($request, $request->attributes->get('ver', Info::DEFAULT_VERSION));
        } finally {
            $apiGroupContext->reset();
        }
    }

    /**
     * Per-client connection config — "how do I connect to this server", not "how do I
     * publish it". See Ufo\McpBundle\ClientManifest\McpClientManifestGeneratorInterface.
     */
    #[Route('/manifest/{ai_name<[\w-]+>}.json', name: 'ufo_mcp_manifest', defaults: ['ver' => Info::DEFAULT_VERSION], methods: ['GET'])]
    #[Route('/{ver<v\d+>}/manifest/{ai_name<[\w-]+>}.json', name: 'ufo_mcp_manifest_ver', methods: ['GET'])]
    public function manifestAction(
        string $aiName,
        Request $request,
        McpClientManifestGeneratorHolder $generators,
        McpAvailabilityCheckerInterface $availabilityChecker,
        McpConfig $config,
        RpcMainConfig $rpcConfig,
    ): Response
    {
        $groupName = $request->attributes->get('api_group', APIGroups::DEFAULT_GROUP);
        if (!$availabilityChecker->isEnabled($groupName)) {
            return new Response('', 404);
        }

        $generator = $generators->get($aiName);
        if ($generator === null) {
            return new JsonResponse(['error' => sprintf('Unknown MCP client manifest "%s".', $aiName)], 404);
        }

        $group = $config->group($groupName);
        $suffix = '/manifest/'.$aiName.'.json';
        $endpointPath = str_ends_with($path = $request->getPathInfo(), $suffix)
            ? substr($path, 0, -strlen($suffix))
            : $path;

        return new JsonResponse($generator->generate(
            $group,
            $request->getSchemeAndHttpHost().$endpointPath,
            $rpcConfig->securityConfig->tokenName,
        ));
    }

    /**
     * MCP Registry publication manifest — see Ufo\McpBundle\DocAdapters\Outputs\ServerJsonAdapter.
     * Unrelated to the MCP protocol's own "initialize" response.
     */
    #[Route('/server.json', name: 'ufo_mcp_server_json', defaults: ['ver' => Info::DEFAULT_VERSION], methods: ['GET'])]
    #[Route('/{ver<v\d+>}/server.json', name: 'ufo_mcp_server_json_ver', methods: ['GET'])]
    public function serverJsonAction(
        Request $request,
        ServerJsonAdapter $serverJsonAdapter,
        McpAvailabilityCheckerInterface $availabilityChecker,
        McpConfig $config,
    ): Response
    {
        $groupName = $request->attributes->get('api_group', APIGroups::DEFAULT_GROUP);
        if (!$availabilityChecker->isEnabled($groupName)) {
            return new Response('', 404);
        }

        $group = $config->group($groupName);
        $suffix = '/server.json';
        $endpointPath = str_ends_with($path = $request->getPathInfo(), $suffix)
            ? substr($path, 0, -strlen($suffix))
            : $path;

        return new JsonResponse($serverJsonAdapter->adapt(
            $group,
            $request->getSchemeAndHttpHost().$endpointPath,
            $request->attributes->get('ver', Info::DEFAULT_VERSION),
        ));
    }
}
