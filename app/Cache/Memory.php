<?php

declare(strict_types=1);

namespace App\Cache;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

/**
 * 内存缓存.
 */
class Memory implements CacheInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var bool
     */
    protected $isReady = false;

    /**
     * @var array
     */
    protected $data = [
        'tasks' => [],
        'ignoreSmTaskids' => [],
    ];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * 是否已经ready.
     */
    public function isReady(): bool
    {
        return $this->isReady;
    }

    /**
     * 获取任务
     */
    public function getTask(int $taskId): ?array
    {
        $this->waitForReady();

        return $this->data['tasks'][$taskId] ?? null;
    }

    /**
     * 获取所有任务
     */
    public function getTasks(): array
    {
        $this->waitForReady();

        return $this->data['tasks'];
    }

    /**
     * 获取所有忽略自监控的任务ID.
     */
    public function getIgnoreSmTaskids(): array
    {
        $this->waitForReady();

        return array_keys($this->data['ignoreSmTaskids']);
    }

    /**
     * 是否忽略自监控.
     */
    public function isIgnoreSm(int $taskId): bool
    {
        $this->waitForReady();

        return array_key_exists($taskId, $this->data['ignoreSmTaskids']);
    }

    /**
     * 覆盖.
     */
    public function cover(array $data): void
    {
        $this->data = $data;

        // 设置ready状态
        $this->isReady = true;
    }

    /**
     * 从文件初始化缓存.
     */
    public function initFromFileCache(): bool
    {
        $cachePath = $this->container->get(ConfigInterface::class)->get('producer.data_sync_file_cache_path');

        if (! is_file($cachePath)) {
            return false;
        }
        $cache = file_get_contents($cachePath);
        $json = json_decode($cache, true);
        if (json_last_error() != JSON_ERROR_NONE || ! is_array($json) || ! isset($json['data'])) {
            return false;
        }

        $this->cover($json['data']);

        return true;
    }

    /**
     * 等待ready.
     */
    protected function waitForReady(): void
    {
        if ($this->isReady) {
            return;
        }

        $timeout = 1;
        $startAt = microtime(true);
        while (microtime(true) - $startAt < $timeout) {
            if ($this->isReady) {
                return;
            }
        }

        // 抛出异常
        throw new WaitTimeoutException();
    }
}
