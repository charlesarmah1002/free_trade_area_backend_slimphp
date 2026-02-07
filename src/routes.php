<?php

use Slim\App;

return function (App $app) {
    (require __DIR__ . "/Routes/BusinessAccount.php")($app);
    (require __DIR__ . "/Routes/Products.php")($app);
};