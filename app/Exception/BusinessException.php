<?php

declare(strict_types=1);

namespace App\Exception;

use Hyperf\Server\Exception\ServerException;
use Throwable;

class BusinessException extends ServerException
{
    public function __construct(int $code = 0, string $message = null, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
