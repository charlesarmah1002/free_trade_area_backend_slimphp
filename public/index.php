<?php
declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$app = AppFactory::create();

$app->add(function (Request $request, $handler) {
    $allowed_origins = [
        'http://localhost:5173',
        'http://100.115.149.56:5173',
    ];

    $origin = $request->getHeaderLine('Origin');

    if ($request->getMethod() === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();

        if (in_array($origin, $allowed_origins)) {
            return $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }

        return $response;
    }

    $response = $handler->handle($request);

    if (in_array($origin, $allowed_origins)) {
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }

    return $response;
});

require __DIR__ . '/../src/database.php';

$app->addBodyParsingMiddleware();

$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write("Hello world!");
    return $response;
});

(require __DIR__ . '/../src/routes.php')($app);

$app->run();