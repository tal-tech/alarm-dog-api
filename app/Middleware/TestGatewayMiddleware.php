<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Cache\CacheInterface;
use App\Constants\ErrorCode;
use App\Support\Response;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TestGatewayMiddleware implements MiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var HttpResponse
     */
    protected $response;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var int
     */
    protected $signTimestampRange = 1800;

    public function __construct(ContainerInterface $container, HttpResponse $response, RequestInterface $request)
    {
        $this->container = $container;
        $this->response = $response;
        $this->request = $request;
        $this->cache = $container->get(CacheInterface::class);
        $this->signTimestampRange = (int) $container->get(ConfigInterface::class)
            ->get('producer.sign_timestamp_range', 1800);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $taskid = (int) $this->request->input('taskid');
        $timestamp = (int) $this->request->input('timestamp');
        $sign = $this->request->input('sign');

        $task = $this->cache->getTask($taskid);

        // taskid是否存在校验
        if (is_null($task)) {
            return $this->json(ErrorCode::UNAUTHORIZED, 'signature invalid', 401);
        }
        // 时间戳校验
        if (abs(time() - $timestamp) > $this->signTimestampRange) {
            return $this->json(ErrorCode::UNAUTHORIZED, 'signature was expired', 401);
        }
        // 签名校验
        if ($sign !== md5($taskid . '&' . $timestamp . $task['token'])) {
            return $this->json(ErrorCode::UNAUTHORIZED, 'signature invalid', 401);
        }

        return $handler->handle($request);
    }

    /**
     * 响应Json.
     *
     * @param int $code
     * @param string $msg
     * @param int $statusCode
     */
    protected function json($code, $msg, $statusCode)
    {
        return $this->response->json(Response::json($code, $msg))->withStatus($statusCode);
    }
}
