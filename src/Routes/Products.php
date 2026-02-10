<?php

declare(strict_types=1);

use Slim\App;

use App\Controllers\ProductsController;

return function (App $app) {
    $app->get('/products', [ProductsController::class, 'get_products']);
    $app->post('/products', [ProductsController::class, 'create_product']);
    $app->get('/products/{id}', [ProductsController::class, 'get_product']);
    $app->post('/products/{id}', [ProductsController::class, 'edit_product']);
    $app->post('/products/{id}', [ProductsController::class, 'delete_product']);
};