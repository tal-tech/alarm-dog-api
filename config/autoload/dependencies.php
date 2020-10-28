<?php

declare(strict_types=1);

use App\Cache\CacheInterface;
use App\Cache\Memory;
use App\Support\StdoutLogger;
use Hyperf\Contract\StdoutLoggerInterface;

return [
    StdoutLoggerInterface::class => StdoutLogger::class,
    CacheInterface::class => Memory::class,
];
