<?php

declare(strict_types=1);

namespace App\Producer\Driver;

use Hyperf\Redis\Redis as HyperfRedis;
use Psr\Container\ContainerInterface;
use Redis as LocalRedis;
use Throwable;

class Redis extends DriverAbstract
{
    /**
     * @var string
     */
    protected $name = 'redis';

    /**
     * @var LocalRedis
     */
    protected $redis;

    public function __construct(ContainerInterface $container, array $config = [])
    {
        parent::__construct($container, $config);

        $this->redis = $container->get(HyperfRedis::class);
    }

    /**
     * 生产消息.
     */
    public function produce(string $payload)
    {
        try {
            $this->redis->lPush($this->config['key_message'], $payload);
        } catch (Throwable $e) {
            $this->logger->error($this->formatter->format($e));
            $this->stdoutLogger->error($e->getMessage());
            throw $e;
        }
    }

    /**
     * 批量生产消息.
     */
    public function batchProduce(array $payloads)
    {
        if ($this->config['batch_size']) {
            foreach (array_chunk($payloads, $this->config['batch_size']) as $chunk) {
                $this->batchProduceAll($chunk);
            }
        } else {
            $this->batchProduceAll($payloads);
        }
    }

    /**
     * 批量生产消息.
     */
    protected function batchProduceAll(array $payloads)
    {
        try {
            $this->redis->lPush($this->config['key_message'], ...$payloads);
        } catch (Throwable $e) {
            $this->logger->error($this->formatter->format($e));
            $this->stdoutLogger->error($e->getMessage());
            throw $e;
        }
    }
}
