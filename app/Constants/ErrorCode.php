<?php

declare(strict_types=1);

namespace App\Constants;

class ErrorCode
{
    // 未获授权
    public const UNAUTHORIZED = 401;

    // 404找不到
    public const NOT_FOUND = 404;

    // 参数错误
    public const INVALID_INPUT = 4000;

    // 任务已停止
    public const TASK_STOPPED = 4001;

    // 任务已暂停
    public const TASK_PAUSED = 4002;

    // 任务达到速率限制
    public const TASK_REACH_RATE_LIMIT = 4010;

    // 等待ready超时
    public const WAIT_READY_TIMEOUT = 1001;
}
