<?php

use App\Middleware\BusinessAccountMiddleware;
use Slim\App;

use App\Controllers\BusinessAccountController;

return function (App $app) {
    $app->post('/business/update', [BusinessAccountController::class, 'update_business_account'])->add(new BusinessAccountMiddleware($app->getResponseFactory()));
    $app->post('/business/create', [BusinessAccountController::class, 'create_business_account']);
    $app->post("/business/verify", [BusinessAccountController::class, 'validate_business_account']);
    $app->post('/business/update-password', [BusinessAccountController::class, 'update_password'])->add(new BusinessAccountMiddleware($app->getResponseFactory()));
};