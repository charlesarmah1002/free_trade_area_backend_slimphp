<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class BusinessAccountMiddleware {
    public function __invoke(Request $request, RequestHandler $handler) {
        
        $authToken = $request->getHeaderLine("Authorization");

        return $handler->handle($request);
    }

    public function authenticateBusiness(Request $request, RequestHandler $handler) {

        return $handler->handle($request);
    }
}