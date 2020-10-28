<?php

declare(strict_types=1);

namespace App\Cache;

use App\Constants\ErrorCode;
use Exception;

class WaitTimeoutException extends Exception
{
    public function __construct(string $message = 'wait for ready timeout', int $code = ErrorCode::WAIT_READY_TIMEOUT)
    {
        parent::__construct($message, $code);
    }
}
