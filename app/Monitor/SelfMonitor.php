<?php

declare(strict_types=1);

namespace App\Monitor;

use App\Cache\CacheInterface;
use App\Monitor\Notification\NotificationAdapter;
use Hyperf\Contract\ConfigInterface;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class SelfMonitor
{
    // 限流降噪的key前缀
    protected const ALARM_COMPRESS_KEY = 'dog-alarm-compress.';

    /**
     * @var ContainerInterface
     */
    protected $container;

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
     * @var bool
     */
    protected $enableAlarm = false;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var NotificationAdapter
     */
    protected $notificationAdapter;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->redis = $container->get(Redis::class);
        $this->logger = $container->get(LoggerFactory::class)->get('self', 'monitor');
        $this->formatter = $container->get(FormatterInterface::class);
        $this->cache = $container->get(CacheInterface::class);
        $this->notificationAdapter = $container->get(NotificationAdapter::class);
        $this->enableAlarm = (bool) $container->get(ConfigInterface::class)->get('monitor.self_monitor_enable');
    }

    /**
     * 发送告警.
     *
     * @param int $taskid
     */
    public function alarm(string $title, string $content, $taskid = null)
    {
        // 如果告警关闭，则不发送告警
        if (! $this->enableAlarm) {
            return;
        }
        // 是否在忽略的告警任务ID名单内
        if (! is_null($taskid) && $this->cache->isIgnoreSm((int) $taskid)) {
            return;
        }

        // 限流降噪
        $compressKey = is_null($taskid) ? sha1($title . $content) : sha1($title . $taskid);
        if (! $this->alarmCompressed($compressKey)) {
            $this->notificationAdapter->send(sprintf("%s\n\n%s", $title, $content));
        }
    }

    /**
     * 记录日志.
     *
     * @param string $level
     * @param string $message
     */
    public function log($level, $message, array $context = [])
    {
        $this->logger->{$level}($message, $context);
    }

    /**
     * 是否告警收敛.
     *
     * @return bool
     */
    protected function alarmCompressed(string $key)
    {
        try {
            return ! $this->redis->set(self::ALARM_COMPRESS_KEY . $key, time(), ['nx', 'ex' => 60]);
        } catch (Throwable $e) {
            $this->logger->error($this->formatter->format($e));
            return false;
        }
    }
}
