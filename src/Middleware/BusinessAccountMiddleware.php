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
use Psr\Http\Message\ResponseFactoryInterface;

class BusinessAccountMiddleware
{
    private ResponseFactoryInterface $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function __invoke(Request $request, RequestHandler $handler)
    {
        $auth = $request->getHeaderLine('Authorization');

        if (empty($auth)) {
            return $this->unauthorizedResponse();
        }

        return $handler->handle($request);
    }

    // public function authenticateBusiness(Request $request, RequestHandler $handler) :Response
    // { 
    //     // grab data from headers
    //     $authHeader = $request->getHeaderLine("Authorization");

    //     // initializing response for error blocks
    //     $response = 

    //     if (empty($authHeader) || !preg_match('/^(\w+)\s+(.*)$/', $authHeader, $matches)) {
    //         $response
    //     }

    //     $token = $matches[2];

    //     // now send the token to the firebase jwt class to extrac the info
    //     $firebaseJWT = new FirebaseJWT;
    //     $extracted_data = $firebaseJWT->validate_token($token);

    //     if (empty($extracted_data) || $extracted_data['error'] == true) {
    //         $response
    //     }
    
    //     return true;
    // }

    private function unauthorizedResponse(): Response
    {
        $response = $this->responseFactory->createResponse(401);

        $response->getBody()->write(json_encode([
            "error" => true,
            "message" => "Unauthorized"
        ]));

        return $response->withHeader("Content-Type_attach", "application/json");
    }
}