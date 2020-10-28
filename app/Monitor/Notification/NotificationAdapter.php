<?php

declare(strict_types=1);

namespace App\Monitor\Notification;

use App\Monitor\Notification\Driver\NotificationInterface;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Throwable;

class NotificationAdapter
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var StdoutLoggerInterface
     */
    protected $stdoutLogger;

    /**
     * @var NotificationInterface[]
     */
    protected $drivers = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerFactory::class)->get('notification', 'monitor');
        $this->stdoutLogger = $container->get(StdoutLoggerInterface::class);

        $this->initDrivers($container);
    }

    /**
     * 发送告警通知.
     */
    public function send(string $content)
    {
        foreach ($this->drivers as $driver) {
            Coroutine::create(function ($driver, $content) {
                /* @var NotificationInterface $driver */
                try {
                    $driver->send($content);
                } catch (Throwable $e) {
                    $this->logger->warning('alarm send failed: ' . $e->getMessage(), [
                        'driver' => $driver->getName(),
                        'content' => $content,
                    ]);
                }
            }, $driver, $content);
        }
    }

    /**
     * 初始化驱动.
     */
    protected function initDrivers(ContainerInterface $container)
    {
        /** @var ConfigInterface */
        $config = $container->get(ConfigInterface::class);

        $drivers = [];
        foreach ($config->get('monitor.default_notification', []) as $name) {
            if (! $config->has('monitor.notifications.' . $name)) {
                $this->stdoutLogger->warning(sprintf('monitor notification driver [%s] not found', $name));
                continue;
            }

            $drivers[$name] = $this->createDriver($config->get('monitor.notifications.' . $name, []));
        }

        $this->drivers = $drivers;
    }

    /**
     * 创建一个驱动.
     *
     * @return NotificationInterface
     */
    protected function createDriver(array $param)
    {
        return make($param['class'], [
            'config' => $param['config'],
        ]);
    }
}
