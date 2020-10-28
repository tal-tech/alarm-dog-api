<?php

declare(strict_types=1);

namespace App\Listener;

use App\Cache\CacheInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeWorkerStart;

/**
 * @Listener
 */
class BeforeWorkerStartListener implements ListenerInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var StdoutLoggerInterface
     */
    protected $stdoutLogger;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->stdoutLogger = $container->get(StdoutLoggerInterface::class);
    }

    public function listen(): array
    {
        return [
            BeforeWorkerStart::class,
        ];
    }

    /**
     * @param BeforeWorkerStart $event
     */
    public function process(object $event)
    {
        if ($this->container->get(CacheInterface::class)->initFromFileCache()) {
            $this->stdoutLogger->info(sprintf('load cache from file#%s at pid %s', $event->workerId, getmypid()));
        } else {
            $this->stdoutLogger->warning(
                sprintf('failed to load cache from file#%s at pid %s', $event->workerId, getmygid())
            );
        }
    }
}
