<?php

use App\Middleware\ExceptionMiddleware;
use App\Middleware\CORSMiddleware;
use Selective\BasePath\BasePathMiddleware;
use Slim\App;

return function (App $app) {
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();
    $app->add(BasePathMiddleware::class);
    $app->add(ExceptionMiddleware::class);
    $app->add(CORSMiddleware::class);
};
