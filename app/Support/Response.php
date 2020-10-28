<?php

declare(strict_types=1);

namespace App\Support;

use stdClass;

class Response
{
    /**
     * 响应json.
     */
    public static function json(int $code = 0, string $msg = 'success', array $data = [], array $extend = []): array
    {
        return array_merge([
            'code' => (int) $code,
            'msg' => $msg,
            'data' => $data ?: new stdClass(),
        ], $extend);
    }
}
