<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\Response;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;

abstract class AbstractController
{
    /**
     * @Inject
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @Inject
     * @var RequestInterface
     */
    protected $request;

    /**
     * @Inject
     * @var ResponseInterface
     */
    protected $response;

    /**
     * 响应成功信息.
     *
     * @param array $data
     * @return PsrResponseInterface
     */
    protected function success($data = [], string $msg = 'success', int $code = 0, array $extend = [])
    {
        $json = Response::json($code, $msg, $data, $extend);

        return $this->response->json($json);
    }

    /**
     * 响应失败信息.
     *
     * @param array $data
     * @return PsrResponseInterface
     */
    protected function failed(string $msg = 'failed', $data = [], int $code = 1, array $extend = [])
    {
        return $this->success($data, $msg, $code, $extend);
    }
}
