<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Cache\CacheInterface;
use App\Constants\ErrorCode;
use App\Monitor\SelfMonitor;
use App\Support\Response;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class GatewayMiddleware implements MiddlewareInterface
{
    // redis key prefix for request
    public const REDIS_KEY_PREFIX_REQUEST = 'dog-req.';

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
     * @Inject
     * @var SelfMonitor
     */
    protected $selfMonitor;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var Redis
     */
    protected $redis;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var FormatterInterface
     */
    protected $formatter;

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
        $this->redis = $container->get(Redis::class);
        $this->logger = $container->get(LoggerFactory::class)->get('middleware');
        $this->formatter = $container->get(FormatterInterface::class);
        $this->selfMonitor = $container->get(SelfMonitor::class);
        $this->signTimestampRange = (int) $container->get(ConfigInterface::class)
            ->get('producer.sign_timestamp_range', 1800);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $taskid = (int) $this->request->input('taskid');
        $timestamp = (int) $this->request->input('timestamp');
        $sign = $this->request->input('sign');

        $task = $this->cache->getTask($taskid);

        $alarmLines = [
            'taskid' => $taskid,
            'timestamp' => $timestamp,
            'sign' => $sign,
            'hostname' => gethostname(),
            'remote_addr' => $request->getServerParams()['remote_addr'] ?? 'unknown',
            'user-agent' => $request->getHeaderLine('user-agent'),
            'content-type' => $request->getHeaderLine('content-type'),
            'body' => (string) $request->getBody(),
        ];

        // taskid是否存在校验
        if (is_null($task)) {
            $this->selfMonitor->alarm(
                'sign validate: task id not found',
                $this->buildAlarmContent($alarmLines),
                $taskid
            );

            return $this->json(ErrorCode::UNAUTHORIZED, 'signature invalid', 401);
        }

        // QPS统计
        try {
            $time = time();
            $key = self::REDIS_KEY_PREFIX_REQUEST . date('Y-m-d-H-i', $time);
            $field = $taskid . '-' . date('s', $time);
            $this->redis->hIncrBy($key, $field, 1);
        } catch (Throwable $e) {
            $this->logger->error($this->formatter->format($e));
        }

        // 时间戳校验
        if (abs(time() - $timestamp) > $this->signTimestampRange) {
            $alarmLines['cur_timestamp'] = time();
            $alarmLines['sign_timestamp_range'] = $this->signTimestampRange;
            $this->selfMonitor->alarm(
                'sign validate: signature was expired',
                $this->buildAlarmContent($alarmLines),
                $taskid
            );

            return $this->json(ErrorCode::UNAUTHORIZED, 'signature was expired', 401);
        }

        // 签名校验
        if ($sign !== md5($taskid . '&' . $timestamp . $task['token'])) {
            $this->selfMonitor->alarm(
                'sign validate: signature invalid',
                $this->buildAlarmContent($alarmLines),
                $taskid
            );

            return $this->json(ErrorCode::UNAUTHORIZED, 'signature invalid', 401);
        }

        return $handler->handle($request);
    }

    /**
     * 构建告警内容.
     */
    protected function buildAlarmContent(array $items): string
    {
        $str = '';
        foreach ($items as $key => $value) {
            $str .= sprintf("%s: %s\n", $key, $value);
        }
        return $str;
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
