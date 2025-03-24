<?php

// Define app routes

use App\Middleware\JwtHandlerMiddleware;
use Slim\App;

return function (App $app) {
    $app->get('/', \App\Action\Home\HomeAction::class)->setName('home');
    $app->get('/ping', \App\Action\Home\PingAction::class);
    $app->post('/auth/login', \App\Action\Auth\LoginAction::class);
    $app->post('/auth/register', \App\Action\Auth\RegisterAction::class);
    $app->post('/auth/logout', \App\Action\Auth\LogoutAction::class)->add(JwtHandlerMiddleware::class);
    $app->post('/auth/logout/{user_email}', \App\Action\Auth\LogoutAnotherAction::class)->add(JwtHandlerMiddleware::class);
    $app->post('/auth/logoutAll', \App\Action\Auth\LogoutAllAction::class)->add(JwtHandlerMiddleware::class);
    $app->delete('/auth/remove/{user_email}', \App\Action\Auth\RemoveAction::class)->add(JwtHandlerMiddleware::class);
    $app->get('/profile/{user_email}', \App\Action\Profile\ProfileAction::class)->add(JwtHandlerMiddleware::class);
    $app->post('/profile/{user_email}', \App\Action\Profile\ProfileModifyAction::class)->add(JwtHandlerMiddleware::class);
    $app->post("/tests/", \App\Action\Tests\TestsGetAction::class)->add(JwtHandlerMiddleware::class);
    $app->post("/tests/{id}", \App\Action\Tests\TestsConcretoGetAction::class)->add(JwtHandlerMiddleware::class);
    $app->post('/productos', \App\Action\Productos\ProductosGetAction::class)->add(JwtHandlerMiddleware::class);
    $app->post('/lotes', \App\Action\Lotes\LotesGetAction::class)->add(JwtHandlerMiddleware::class);
    $app->post('/tests/registerMuestreo/{id_test}', \App\Action\Tests\MuestreoRegistrarAction::class)->add(JwtHandlerMiddleware::class);
    $app->post('/unidades/register', \App\Action\Unidades\UnidadRegisterAction::class)->add(JwtHandlerMiddleware::class);
    $app->get('/unidades/{id_test}', \App\Action\Unidades\UnidadesGetTest::class)->add(JwtHandlerMiddleware::class);
};
