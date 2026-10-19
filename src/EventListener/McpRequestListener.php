<?php

declare(strict_types=1);

namespace Ufo\McpBundle\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\TokenHolders\HttpTokenHolder;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\McpBundle\Availability\McpAvailabilityCheckerInterface;
use Ufo\McpBundle\Controller\McpController;
use Ufo\McpBundle\Routing\McpRouteImport;
use Ufo\RpcError\ISecurityExceptionInterface;
use Ufo\RpcError\RpcTokenNotSentException;
use Ufo\RpcObject\RPC\APIGroups;
use Ufo\RpcObject\RPC\Info;

use function in_array;
use function is_array;

/**
 * Single gate in front of every McpController action: sets the API group from the
 * matched route (no "ufo_mcp.groups" entry needed — see McpConfig::group()), answers 404
 * for a disabled group and for an API version the group doesn't have, and checks the token through the
 * core IRpcSecurity — API token for the MCP endpoint itself, doc token (honouring
 * "protected_doc") for server.json and client manifests. The action is identified by
 * the controller method, not the route name, so a "name_prefix" on the route import
 * can't bypass the check. A failed token check answers 401 (no token) / 403 (invalid
 * token) itself: the core error listener only handles the core RPC routes, so without
 * this the security exception would surface as a 500 on the MCP routes.
 */
#[AsEventListener(event: KernelEvents::CONTROLLER)]
final readonly class McpRequestListener
{
    public function __construct(
        private McpAvailabilityCheckerInterface $availabilityChecker,
        private ApiGroupContext $apiGroupContext,
        private IRpcSecurity $rpcSecurity,
        private RpcMainConfig $rpcConfig,
        private ServiceMap $serviceMap,
    ) {}

    public function __invoke(ControllerEvent $event): void
    {
        $controller = $event->getController();
        if (!is_array($controller) || !$controller[0] instanceof McpController) return;

        $request = $event->getRequest();
        $group = $request->attributes->get(ApiGroupContext::ROUTE_GROUP, APIGroups::DEFAULT_GROUP);
        $this->apiGroupContext->setGroup($group, $request->attributes->get(McpRouteImport::NAME_PREFIX_ATTR));

        if (!$this->availabilityChecker->isEnabled($group)
            || !in_array($request->attributes->get('ver', Info::DEFAULT_VERSION), $this->serviceMap->getVersions(), true)
        ) {
            $this->notFound($event);
            return;
        }

        // CORS preflight carries no token; the transport's CorsMiddleware answers it.
        if ($request->isMethod(Request::METHOD_OPTIONS)) return;

        $this->rpcSecurity->setTokenHolder(new HttpTokenHolder($this->rpcConfig, $request));
        try {
            $controller[1] === McpController::ENDPOINT_ACTION
                ? $this->rpcSecurity->isValidApiRequest()
                : $this->rpcSecurity->isValidDocRequest();
        } catch (ISecurityExceptionInterface $e) {
            $status = $e instanceof RpcTokenNotSentException ? Response::HTTP_UNAUTHORIZED : Response::HTTP_FORBIDDEN;
            $message = $e->getMessage();
            $event->setController(static fn(): Response => new JsonResponse(['error' => $message], $status));
        }
    }

    private function notFound(ControllerEvent $event): void
    {
        $event->setController(static fn(): Response => new Response('', Response::HTTP_NOT_FOUND));
    }
}
