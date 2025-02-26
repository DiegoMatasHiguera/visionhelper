<?php

// Define app routes

use App\Middleware\JwtHandlerMiddleware;
use Slim\App;

return function (App $app) {
    $app->get('/', \App\Action\Home\HomeAction::class)->setName('home');
    $app->get('/ping', \App\Action\Home\PingAction::class);
    $app->post('/auth/login', \App\Action\Auth\LoginAction::class);
    $app->post('/auth/logout', \App\Action\Auth\LogoutAction::class)->add(JwtHandlerMiddleware::class);
    $app->get('/profile/{user_email}', \App\Action\Profile\ProfileAction::class)->add(JwtHandlerMiddleware::class);
};
