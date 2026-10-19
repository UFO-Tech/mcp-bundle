<?php

declare(strict_types=1);

/*
 * Front controller of the fixture app for the end-to-end tests: `php -S 127.0.0.1:<port> tests/Fixtures/public/index.php`.
 */

use Symfony\Component\HttpFoundation\Request;
use Ufo\McpBundle\Tests\Fixtures\TestKernel;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$kernel = new TestKernel((string) (getenv('APP_ENV') ?: 'test'), false);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
