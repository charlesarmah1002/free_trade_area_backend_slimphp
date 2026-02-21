<?php

declare(strict_types=1);

namespace App\Middleware;

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Utilities\FirebaseJWT;
use Exception;
use Psr\Http\Message\ResponseFactoryInterface;

class AuthMiddleware {
    private ResponseFactoryInterface $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function __invoke(Request $request, RequestHandler $handler)
    {
        $authCookie = $request->getCookieParams();

        if (!isset($authCookie['token'])) {
            return new \Slim\Psr7\Response(401);
        }

        try {
            $jwt = new FirebaseJWT;
            $valid = $jwt->validate_token($authCookie['token']);
            $request = $request->withAttribute('valid_token', $valid);
        } catch (Exception $e) {
            return new \Slim\Psr7\Response(401);
        }

        return $handler->handle($request);
    }
}