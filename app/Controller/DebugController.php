<?php

declare(strict_types=1);

namespace App\Controller;

use App\Cache\CacheInterface;
use Hyperf\Di\Annotation\Inject;

class DebugController extends AbstractController
{
    /**
     * @Inject
     * @var CacheInterface
     */
    protected $cache;

    /**
     * 所有任务
     */
    public function tasks()
    {
        $resp = [
            'pid' => getmypid(),
            'tasks' => $this->cache->getTasks(),
        ];

        return $this->success($resp);
    }

    /**
     * 获取自监控忽略任务ID.
     */
    public function ignoreTaskIds()
    {
        $resp = [
            'pid' => getmypid(),
            'taskids' => $this->cache->getIgnoreSmTaskids(),
        ];

        return $this->success($resp);
    }

    /**
     * 获取自监控忽略任务ID.
     */
    public function isReady()
    {
        $resp = [
            'pid' => getmypid(),
            'is_ready' => $this->cache->isReady(),
        ];

        return $this->success($resp);
    }
}
