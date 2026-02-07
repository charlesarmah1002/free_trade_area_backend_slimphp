<?php

use Slim\App;

use App\Controllers\BusinessAccountController;

return function (App $app) {
    $app->post('/business', [BusinessAccountController::class, 'update_business_account']);
    $app->post('/business/create', [BusinessAccountController::class, 'create_business_account']);
    $app->post("/business/verify", [BusinessAccountController::class, 'validate_business_account']);
};