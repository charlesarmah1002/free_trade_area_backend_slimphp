<?php
declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Cloudinary\Configuration\Configuration;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$app = AppFactory::create();

// ✅ CORS middleware — must be added before routes
$app->add(function (Request $request, $handler) {
    $allowed_origins = [
        'http://localhost:5173',
        'http://100.115.149.56:5173',
    ];

    $origin = $request->getHeaderLine('Origin');

    $response = $handler->handle($request);

    // Handle preflight OPTIONS request
    if ($request->getMethod() === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write('');
    }

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

// ✅ Add OPTIONS route for preflight requests
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello world!");
    return $response;
});

(require __DIR__ . '/../src/routes.php')($app);

$app->run();