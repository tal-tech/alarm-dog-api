<?php

declare(strict_types=1);

namespace App\Signal;

use App\Producer\ProducerFactory;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Process\ProcessManager;
use Hyperf\Signal\SignalHandlerInterface;
use Psr\Container\ContainerInterface;
use Swoole\Server;

class WorkerStopHandler implements SignalHandlerInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var ConfigInterface
     */
    protected $config;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->config = $container->get(ConfigInterface::class);
    }

    public function listen(): array
    {
        return [
            [self::WORKER, SIGTERM],
            [self::WORKER, SIGINT],
        ];
    }

    public function handle(int $signal): void
    {
        ProcessManager::setRunning(false);

        /** @var Server */
        $server = $this->container->get(Server::class);

        // 停止生产者
        foreach ($this->container->get(ProducerFactory::class)->gets() as $producer) {
            $producer->stop();
        }

        $server->stop();
    }
}
