<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Utils\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = Context::override(ResponseInterface::class, function (ResponseInterface $response) use ($request) {
            return $response->withHeader('Access-Control-Allow-Origin', $request->getHeaderLine('origin') ?: '*')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Allow-Headers', 'Authorization,*')
                ->withHeader('Access-Control-Expose-Headers', 'Authorization,*')
                ->withHeader('Access-Control-Max-Age', 86400);
        });

        if ($request->getMethod() == 'OPTIONS') {
            return $response;
        }

        return $handler->handle($request);
    }
}
