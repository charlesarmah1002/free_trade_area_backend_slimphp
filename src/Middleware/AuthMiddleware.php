<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseFactoryInterface;
use App\Utilities\FirebaseJWT;
use Exception;

class AuthMiddleware
{
    private ResponseFactoryInterface $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function __invoke(Request $request, RequestHandler $handler)
    {
        $cookies = $request->getCookieParams();

        if (!isset($cookies['access_token'])) {
            $response = $this->responseFactory->createResponse(401);
            $response->getBody()->write(json_encode([
                'error' => 'Access token missing'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            $jwt = new FirebaseJWT();
            $valid = $jwt->validate_token($cookies['access_token']);

            $request = $request->withAttribute('valid_token', $valid);

        } catch (Exception $e) {
            $response = $this->responseFactory->createResponse(401);
            $response->getBody()->write(json_encode([
                'error' => 'Invalid or expired token'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}