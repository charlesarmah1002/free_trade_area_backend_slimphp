<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Utilities\FirebaseJWT;

class BusinessAccountMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler)
    {


        return $handler->handle($request);
    }

    public function authenticateBusiness(Request $request, RequestHandler $handler)
    {
        // grab data from headers
        $authHeader = $request->getHeaderLine("Authorization");

        if (empty($authHeader) || !preg_match('/^(\w+)\s+(.*)$/', $authHeader, $matches)) {
            return false;
        }

        $token = $matches[2];

        // now send the token to the firebase jwt class to extrac the info
        $firebaseJWT = new FirebaseJWT;
        $extracted_data = $firebaseJWT->validate_token($token);

        if (empty($extracted_data) || $extracted_data['error'] == true) {
            return false;
        }
        
        // todo: decide middleware implementation
        return true;
    }
}