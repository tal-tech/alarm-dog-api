<?php

declare(strict_types=1);
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');

Router::addGroup('/alarm', function () {
    Router::addRoute(['POST'], '/report', 'App\Controller\AlarmController@report');
}, [
    'middleware' => [
        \App\Middleware\GatewayMiddleware::class,
    ],
]);

Router::addGroup('/alarm', function () {
    Router::addRoute(['POST'], '/aliyun', 'App\Controller\AlarmController@aliyun');
    Router::addRoute(['POST'], '/grafana', 'App\Controller\AlarmController@grafana');
    Router::addRoute(['GET'], '/falcon', 'App\Controller\AlarmController@falcon');
    Router::addRoute(['POST'], '/arms', 'App\Controller\AlarmController@rawBody');
    Router::addRoute(['GET', 'POST'], '/rawbody', 'App\Controller\AlarmController@rawBody');
    Router::addRoute(['GET', 'POST'], '/jsonbody', 'App\Controller\AlarmController@jsonBody');
}, [
    'middleware' => [
        \App\Middleware\GatewayFixedMiddleware::class,
    ],
]);

Router::addRoute(['POST'], '/alarm/test', 'App\Controller\AlarmController@test', [
    'middleware' => [
        \App\Middleware\TestGatewayMiddleware::class,
    ],
]);

Router::addGroup('/debug', function () {
    Router::addRoute(['GET'], '/tasks', 'App\Controller\DebugController@tasks');
    Router::addRoute(['GET'], '/ignoretaskids', 'App\Controller\DebugController@ignoreTaskIds');
    Router::addRoute(['GET'], '/isready', 'App\Controller\DebugController@isReady');
}, [
    'middleware' => [
        \App\Middleware\LocalMiddleware::class,
    ],
]);
