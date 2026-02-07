<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TestController {
    public function test_function(Request $request, Response $response){
        $response->getBody()->write("This is a route that I am testing");
        return $response->withHeader("Content-Type", "application/json")->withStatus(200);
    }
}