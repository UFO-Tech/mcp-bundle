<?php

declare(strict_types=1);

namespace Ufo\McpBundle\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\McpBundle\EventListener\McpRequestListener;

/**
 * McpRequestListener runs for every controller of the app, but touches only McpController.
 */
final class ListenerScopeTest extends McpKernelTestCase
{
    public function testOtherControllersAreLeftAlone(): void
    {
        self::bootKernel();
        $listener = self::getContainer()->get(McpRequestListener::class);
        $groupContext = self::getContainer()->get(ApiGroupContext::class);
        $groupContext->reset();

        $controller = static fn(): Response => new Response('app');
        $request = Request::create('http://localhost/anything', 'POST');
        $request->attributes->set(ApiGroupContext::ROUTE_GROUP, 'off');
        $event = new ControllerEvent(self::$kernel, $controller, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener($event);

        self::assertSame($controller, $event->getController(), 'no 404 / 401 for a non-MCP route, even in a disabled group');
        self::assertSame('general', $groupContext->getGroup());
    }
}
