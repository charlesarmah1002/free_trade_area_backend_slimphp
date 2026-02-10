<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class BusinessAccountMiddleware implements MiddlewareInterface {
    public function auth(Request $request, Handler $handler) {
        $auth = $request->getHeaderLine('Authorization');

        return $handler->handle($request);
    }
}