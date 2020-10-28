<?php

declare(strict_types=1);

use App\Producer\Driver\Kafka;
use App\Producer\Driver\MqProxy;
use App\Producer\Driver\Redis;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Hyperf\Guzzle\RetryMiddleware;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext;

return [
    'default_driver' => env('PRODUCER_DEFAULT_DRIVER', 'redis'),
    // 签名时间戳误差，单位秒
    'sign_timestamp_range' => (int) env('PRODUCER_SIGN_TIMESTAMP_RANGE', 1800),
    // 任务默认限流值
    'default_rate_limit' => (int) env('PRODUCER_DEFAULT_RATE_LIMIT', 200),
    // 数据同步时间间隔，单位毫秒
    'data_sync_interval' => (int) env('PRODUCER_DATA_SYNC_INTERVAL', 10000),
    // 数据同步缓存路径
    'data_sync_file_cache_path' => env('PRODUCER_DATA_SYNC_FILE_CACHE_PATH', BASE_PATH . '/runtime/alarm-dog-data.json'),

    'drivers' => [
        'mqproxy' => [
            'class' => MqProxy::class,
            'config' => [
                'proxy' => explode(',', env('PRODUCER_MQPROXY_PROXY', 'http://mqproxy:8088')),
                'appid' => env('PRODUCER_MQPROXY_APPID'),
                'appkey' => env('PRODUCER_MQPROXY_APPKEY'),
                'topic' => env('PRODUCER_MQPROXY_TOPIC', 'chatpush'),
                'batch_size' => (int) env('PRODUCER_MQPROXY_BATCH_SIZE', 1000),
                'guzzle' => [
                    // guzzle原生配置选项
                    'options' => [
                        'base_uri' => null,
                        'timeout' => 3.0,
                        'verify' => false,
                        'http_errors' => false,
                        'headers' => [
                            'Connection' => 'keep-alive',
                        ],
                        // hyperf集成guzzle的swoole配置选项
                        'swoole' => [
                            'timeout' => 10,
                            'socket_buffer_size' => 1024 * 1024 * 2,
                        ],
                    ],
                    // guzzle中间件配置
                    'middlewares' => [
                        // 失败重试中间件
                        'retry' => function () {
                            return make(RetryMiddleware::class, [
                                'retries' => 1,
                                'delay' => 10,
                            ])->getMiddleware();
                        },
                        // 请求日志记录中间件
                        'logger' => function () {
                            // $format中{response}调用$response->getBody()会导致没有结果输出
                            $format = ">>>>>>>>\n{request}\n<<<<<<<<\n{res_headers}\n--------\n{error}";
                            $formatter = new MessageFormatter($format);
                            $logger = ApplicationContext::getContainer()->get(LoggerFactory::class)->get('mqproxy');

                            return Middleware::log($logger, $formatter, 'debug');
                        },
                    ],
                    // hyperf集成guzzle的连接池配置选项
                    'pool' => [
                        'option' => [
                            'max_connections' => (int) env('PRODUCER_MQPROXY_POOL_SIZE', 10),
                        ],
                    ],
                ],
            ],
        ],
        'redis' => [
            'class' => Redis::class,
            'config' => [
                'key_message' => env('PRODUCER_REDIS_KEY_MESSAGE', 'alarm-dog.queue.message'),
                'instance' => env('PRODUCER_REDIS_INSTANCE', 'default'),
                'batch_size' => (int) env('PRODUCER_REDIS_BATCH_SIZE', 1000),
            ],
        ],
        'kafka' => [
            'class' => Kafka::class,
            'config' => [
                'brokers' => env('PRODUCER_KAFKA_BROKERS', 'localhost1:9092,localhost2:9092'),
                'topic' => env('PRODUCER_KAFKA_TOPIC', 'alarm-dog'),
                // 异步提交时长，单位：ms
                'flush_timeout' => env('PRODUCER_KAFKA_FLUSH_TIMEOUT', 1000),
            ],
        ],
    ],
];
