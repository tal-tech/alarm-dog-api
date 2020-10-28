<?php

declare(strict_types=1);
$middlewares = [
    'http' => [
    ],
];

if (env('MIDDLEWARE_ENABLE_CORS')) {
    $middlewares['http'][] = \App\Middleware\CorsMiddleware::class;
}

return $middlewares;
