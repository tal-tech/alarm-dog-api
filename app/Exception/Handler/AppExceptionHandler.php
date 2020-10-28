<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Exception\AppException;
use App\Support\Response;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class AppExceptionHandler extends ExceptionHandler
{
    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var bool
     */
    protected $appEnv = 'prod';

    public function __construct(StdoutLoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->appEnv = config('app_env', 'prod');
    }

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $this->logger->error(sprintf('%s[%s] in %s', $throwable->getMessage(), $throwable->getLine(), $throwable->getFile()));
        $this->logger->error($throwable->getTraceAsString());

        // 拦截强制转为Json
        $code = $throwable->getCode() ?: 1;
        $msg = $throwable->getMessage() . '(' . $throwable->getCode() . ')';
        $data = [];
        $extend = [];
        if ($throwable instanceof AppException) {
            $data = $throwable->getContext();
        }

        // 开发模式注入debug信息
        $this->withDebug($extend, $throwable);

        $json = Response::json($code, $msg, $data, $extend);
        $text = json_encode($json);
        return $response->withHeader('Content-Type', 'application/json')->withBody(new SwooleStream($text));
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }

    /**
     * 输出debug信息.
     */
    protected function withDebug(array &$extend, Throwable $e): void
    {
        // 仅dev时开启
        if ($this->appEnv != 'dev') {
            return;
        }
        $extend['debug'] = [
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
            'previous' => $e->getPrevious(),
        ];
    }
}
