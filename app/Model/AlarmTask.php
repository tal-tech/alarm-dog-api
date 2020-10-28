<?php

declare(strict_types=1);

namespace App\Model;

use App\Cache\CacheInterface;
use App\Constants\ErrorCode;
use App\Exception\AppException;
use App\Monitor\SelfMonitor;
use App\Producer\ProducerFactory;
use GuzzleHttp\Psr7\Query;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Redis\Redis;
use Ramsey\Uuid\Uuid;
use Throwable;

class AlarmTask extends Model
{
    // 已停止
    public const STATUS_STOPPED = 0;

    // 运行中
    public const STATUS_RUNNING = 1;

    // 已暂停
    public const STATUS_PAUSE = 2;

    // 通知
    public const LEVEL_NOTICE = 0;

    // 警告
    public const LEVEL_WARNING = 1;

    // 错误
    public const LEVEL_ERROR = 2;

    // 紧急
    public const LEVEL_EMERGENCY = 3;

    // redis key prefix for task rate limit
    public const REDIS_KEY_PREFIX_RATE_LIMIT = 'dog-prod.';

    /**
     * 告警级别.
     *
     * @var array
     */
    public static $levels = [
        self::LEVEL_NOTICE => '通知',
        self::LEVEL_WARNING => '警告',
        self::LEVEL_ERROR => '错误',
        self::LEVEL_EMERGENCY => '紧急',
    ];

    protected $table = 'alarm_task';

    protected $fillable = [
        'name', 'token', 'secret', 'department_id', 'flag_save_db', 'enable_workflow', 'enable_filter',
        'enable_compress', 'enable_upgrade', 'enable_recovery', 'status', 'created_by', 'created_at', 'updated_at',
        'props',
    ];

    protected $casts = [
        'props' => 'array',
    ];

    protected $timestamp = false;

    /**
     * @Inject
     * @var SelfMonitor
     */
    protected $selfMonitor;

    /**
     * @Inject
     * @var Redis
     */
    protected $redis;

    /**
     * @Inject
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @Inject
     * @var ProducerFactory
     */
    protected $producerFactory;

    /**
     * 生产消息.
     *
     * @param array $param
     * @return array
     */
    public function produce($param)
    {
        $this->throwOnStop($param);
        $this->produceTaskRateLimit((int) $param['taskid']);

        $uuid = (string) Uuid::uuid4();
        $data = [
            'taskid' => (int) $param['taskid'],
            'notice_time' => (int) $param['notice_time'],
            'level' => (int) $param['level'],
            'ctn' => $param['ctn'],
            'uuid' => $uuid,
        ];

        $receiver = $this->formatReceiver($param);
        if (! empty($receiver)) {
            $data['receiver'] = $receiver;
        }

        $producer = $this->producerFactory->get();

        // 生产消息到队列
        $payload = $producer->pack($data);
        try {
            $producer->produce($payload);
        } catch (Throwable $e) {
            // 如果是kafka/mqproxy，重试另外一种方式
            try {
                if ($producer->getName() === 'mqproxy') {
                    $this->producerFactory->get('kafka')->produce($payload);
                } elseif ($producer->getName() === 'kafka') {
                    $this->producerFactory->get('mqproxy')->produce($payload);
                } else {
                    throw $e;
                }
            } catch (Throwable $eRetry) {
                $message = sprintf("driver: %s\npayload: %s", $producer->getName(), $payload);
                $this->selfMonitor->alarm('message produce error', $message, $param['taskid']);

                $this->selfMonitor->log('error', 'message produce error', [
                    'driver' => $producer->getName(),
                    'payload' => $data,
                ]);

                // 交由框架处理
                throw $eRetry;
            }
        }

        return [
            'uuid' => $uuid,
            'report_time' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 生产Grafana渠道的告警.
     *
     * @param array $param ['taskid' => '', ...]
     * @param array $body ['msgtype' => '', 'link' => [...]]
     */
    public function produceForGrafana($param, $body)
    {
        if (empty($body['msgtype'])) {
            throw new AppException('field `msgtype` is required', [], null, ErrorCode::INVALID_INPUT);
        }

        $msgType = $body['msgtype'];
        if (empty($body[$msgType])) {
            throw new AppException(
                sprintf('field `%s` is required when msgtype is %s', $msgType, $msgType),
                [],
                null,
                ErrorCode::INVALID_INPUT
            );
        }

        $param['ctn'] = $body[$msgType];
        $param['ctn']['grafanaUrl'] = $this->getUrlInGrafana($body[$msgType][$msgType == 'link' ? 'messageUrl' : 'singleURL']);
        $param['ctn']['text'] = str_replace('\\n', "\n", $param['ctn']['text']);
        $param['ctn']['msgtype'] = $msgType;

        return $this->produce($param);
    }

    /**
     * 生产阿里云渠道的告警.
     *
     * @param array $param ['taskid' => '', ...]
     * @param array $body ['alertName' => ''...]
     */
    public function produceForAliyun($param, $body)
    {
        $ctn = $body;
        unset($ctn['signature']);

        // dimensions解析
        foreach (explode(',', trim($ctn['dimensions'], '{}')) as $dimension) {
            $parts = explode('=', $dimension);
            $key = trim($parts[0]);
            $ctn[$key] = empty($parts[1]) ? null : trim($parts[1]);
            $ctn[$key . '_urlencode'] = urlencode($ctn[$key]);
        }

        // expiression解析
        if (preg_match('/^\$(\w+)(.*?)$/', $ctn['expression'], $matches)) {
            $map = [
                'Maximum' => '最大值',
                'Minimum' => '最小值',
                'Average' => '平均值',
            ];
            $ctn['condition'] = $map[$matches[1]] ?? 'UNKNOWN';
            $ctn['expiression_condition'] = $matches[1];
            $ctn['threshold'] = $matches[2];
        }

        $ctn['date'] = date('Y-m-d H:i:s', $ctn['timestamp'] / 1000);

        $ctn['ruleId_urlencode'] = urlencode($ctn['ruleId']);

        $param['ctn'] = $ctn;

        return $this->produce($param);
    }

    /**
     * 任务停止时抛出异常.
     * @param mixed $param
     */
    protected function throwOnStop($param)
    {
        $status = $this->cache->getTask((int) $param['taskid'])['status'];
        // 已停止，抛出异常
        if ($status == static::STATUS_STOPPED) {
            throw new AppException('task was stopped', [], null, ErrorCode::TASK_STOPPED);
        }
        // 已暂停，抛出异常
        if ($status == static::STATUS_PAUSE) {
            throw new AppException('task was paused', [], null, ErrorCode::TASK_PAUSED);
        }
    }

    /**
     * 消息限流，出发限制会抛出异常.
     *
     * @param int $taskId 任务id
     */
    protected function produceTaskRateLimit(int $taskId)
    {
        $isReachRateLimit = false;
        $taskRateLimit = $this->cache->getTask($taskId)['props']['rate_limit'];
        try {
            $time = time();
            $key = self::REDIS_KEY_PREFIX_RATE_LIMIT . date('Y-m-d-H-i', $time);
            $field = $taskId . '-' . date('s', $time);
            $script = "
                local key = KEYS[1]
                local field = ARGV[1]
                local taskRateLimit = tonumber(ARGV[2])
                local rateLimit = redis.call('HGET', key, field)
                if rateLimit == false or tonumber(rateLimit) < taskRateLimit then
                    return redis.call('HINCRBY', key, field, 1)
                else
                    return taskRateLimit
                end
            ";
            $reqRate = $this->redis->eval($script, [$key, $field, $taskRateLimit], 1);
            if ($reqRate == $taskRateLimit) {
                $message = sprintf(
                    "taskid: %s\nrate_limit: %s\ntime: %s",
                    $taskId,
                    $taskRateLimit,
                    date('Y-m-d H:i:s', $time)
                );
                $this->selfMonitor->alarm('producing message reat limit', $message, $taskId);
                $this->selfMonitor->log('info', 'producing message reat limit', [
                    'taskid' => $taskId,
                    'rate_limit' => $taskRateLimit,
                ]);
                $isReachRateLimit = true;
            }
        } catch (Throwable $e) {
            $this->selfMonitor->log('error', 'redis error', [
                'method' => 'productTaskRateLimit',
                'message' => $e,
            ]);
        } finally {
            // 达到速率限制，抛出异常终止生产
            if ($isReachRateLimit) {
                throw new AppException(
                    sprintf('taskid: %s reach reat limit! current request rate is %s, ', $taskId, $taskRateLimit),
                    [
                        'taskid' => $taskId,
                        'rate_limit' => $taskRateLimit,
                    ],
                    null,
                    ErrorCode::TASK_REACH_RATE_LIMIT
                );
            }
        }
    }

    /**
     * 格式化Receiver的数据.
     *
     * @param array $param
     * @return array
     */
    protected function formatReceiver($param)
    {
        $receiver = [];
        if (! empty($param['receiver'])) {
            // 判断是否设置告警组
            if (! empty($param['receiver']['alarmgroup'])) {
                // 判断告警组数据是否合法
                $groupIds = $param['receiver']['alarmgroup'];
                if (! is_array($groupIds)) {
                    throw new AppException(
                        'receiver.alarmgroup must be a JSON Array',
                        [],
                        null,
                        ErrorCode::INVALID_INPUT
                    );
                }
                if (AlarmGroup::whereIn('id', $groupIds)->count() != count($groupIds)) {
                    throw new AppException(
                        'receiver.alarmgroup is invalid',
                        [],
                        null,
                        ErrorCode::INVALID_INPUT
                    );
                }
                $receiver['alarmgroup'] = $groupIds;
            }
            // 判断是否设置自定义通知渠道
            if (! empty($param['receiver']['channels'])) {
                $channels = [];

                // 用户ID，用于判断用户是否存在
                $uids = [];
                foreach (AlarmGroup::$availableChannelsUser as $channel) {
                    if (empty($param['receiver']['channels'][$channel])) {
                        continue;
                    }
                    if (! is_array($param['receiver']['channels'][$channel])) {
                        throw new AppException(
                            sprintf('receiver.channels.%s must be a JSON Array', $channel),
                            [],
                            null,
                            ErrorCode::INVALID_INPUT
                        );
                    }
                    // 告警接收人必须为数字
                    foreach ($param['receiver']['channels'][$channel] as $uid) {
                        if (! is_numeric($uid)) {
                            throw new AppException(
                                sprintf('receiver.channels.%s[%s] must be number', $channel, $uid),
                                [],
                                null,
                                ErrorCode::INVALID_INPUT
                            );
                        }
                        $uids[] = $uid;
                    }
                    $channels[$channel] = $param['receiver']['channels'][$channel];
                }
                // 校验用户是否存在以及写入未入库的用户
                $this->getInstance(User::class)->saveIncrementUsers($uids);

                // 机器人类型
                foreach (AlarmGroup::$availableChannelsRobot as $channel) {
                    if (empty($param['receiver']['channels'][$channel])) {
                        continue;
                    }
                    if (! is_array($param['receiver']['channels'][$channel])) {
                        throw new AppException(
                            sprintf('receiver.channels.%s must be a JSON Array', $channel),
                            [],
                            null,
                            ErrorCode::INVALID_INPUT
                        );
                    }
                    // 钉钉群通知必须正确设置webhook等参数
                    $robots = [];
                    foreach ($param['receiver']['channels'][$channel] as $robot) {
                        if (empty($robot['webhook'])) {
                            throw new AppException(
                                sprintf('receiver.channels.%s.* webhook is required', $channel),
                                [],
                                null,
                                ErrorCode::INVALID_INPUT
                            );
                        }
                        if (empty($robot['secret'])) {
                            throw new AppException(
                                sprintf('receiver.channels.%s.* secret is required', $channel),
                                [],
                                null,
                                ErrorCode::INVALID_INPUT
                            );
                        }
                        $robots[] = [
                            'webhook' => $robot['webhook'],
                            'secret' => $robot['secret'],
                        ];
                    }
                    $channels[$channel] = $robots;
                }

                // webhook
                if (! empty($param['receiver']['channels'][AlarmGroup::CHANNEL_WEBHOOK])) {
                    $channel = AlarmGroup::CHANNEL_WEBHOOK;
                    if (! filter_var($param['receiver']['channels'][$channel]['url'], FILTER_VALIDATE_URL)) {
                        throw new AppException(
                            sprintf('receiver.channels.%s.url must be a valid url', $channel),
                            [],
                            null,
                            ErrorCode::INVALID_INPUT
                        );
                    }
                    $channels[$channel] = [
                        'url' => $param['receiver']['channels'][$channel]['url'],
                    ];
                }

                if (! empty($channels)) {
                    $receiver['channels'] = $channels;
                }
            }
        }

        return $receiver;
    }

    /**
     * 从url中提取granfana的链接.
     * @param mixed $url
     */
    protected function getUrlInGrafana($url)
    {
        $parsed = parse_url($url);
        if (empty($parsed['query'])) {
            return '';
        }

        $query = Query::parse($parsed['query']);

        return $query['url'];
    }
}
