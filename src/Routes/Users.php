<?php

declare(strict_types=1);

namespace App\Routes;

use Slim\App;
use App\Controllers\UsersController;
use App\Middleware\AuthMiddleware;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/users', function (RouteCollectorProxy $group){
        $group->post('/register', [UsersController::class, 'create_user_account']);
        $group->post('/login', [UsersController::class, 'verify_user_account']);
    });
};