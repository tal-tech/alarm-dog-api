<?php

declare(strict_types=1);

namespace App\Cache;

interface CacheInterface
{
    /**
     * 是否已经ready.
     */
    public function isReady(): bool;

    /**
     * 获取任务
     */
    public function getTask(int $taskId): ?array;

    /**
     * 获取所有任务
     */
    public function getTasks(): array;

    /**
     * 覆盖.
     */
    public function cover(array $data): void;

    /**
     * 获取所有忽略自监控的任务ID.
     */
    public function getIgnoreSmTaskids(): array;

    /**
     * 是否忽略自监控.
     */
    public function isIgnoreSm(int $taskId): bool;

    /**
     * 从文件初始化缓存.
     */
    public function initFromFileCache(): bool;
}
