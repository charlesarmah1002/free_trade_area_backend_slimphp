<?php

declare(strict_types=1);

use Slim\App;
use App\Controllers\ProductsController;
use App\Middleware\AuthMiddleware;
use App\Middleware\BusinessAccountMiddleware;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {

    $app->group('/products', function (RouteCollectorProxy $group) {

        $group->get('', [ProductsController::class, 'get_products']);

        $group->post('', [ProductsController::class, 'create_product']);

        $group->get('/{id}', [ProductsController::class, 'get_product']);

        $group->post('/{id}', [ProductsController::class, 'edit_product']);

        $group->delete('/{id}', [ProductsController::class, 'delete_product']);

    })
    // Apply middleware to ALL /products routes
    ->add(new AuthMiddleware($app->getResponseFactory()));
};
