<?php

declare(strict_types=1);
use Hyperf\Watcher\Driver\FswatchDriver;
use Hyperf\Watcher\Driver\ScanFileDriver;

return [
    'driver' => shell_exec('which fswatch') ? FswatchDriver::class : ScanFileDriver::class,
    'bin' => 'php',
    'watch' => [
        'dir' => ['app', 'config'],
        'file' => ['.env'],
        'scan_interval' => 2000,
    ],
];
