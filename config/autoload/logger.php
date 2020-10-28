<?php

declare(strict_types=1);
return [
    'default' => [
        'handler' => [
            'class' => Monolog\Handler\RotatingFileHandler::class,
            'constructor' => [
                'filename' => env('LOG_BASE_PATH', BASE_PATH . '/runtime/logs') . '/hyperf.log',
                'level' => Monolog\Logger::toMonologLevel(env('LOG_LEVEL_DEFAULT', 'WARNING')),
                'maxFiles' => (int) env('LOG_MAX_FILES_DEFAULT', 7),
            ],
        ],
        'formatter' => [
            'class' => env('LOG_FORMATTER_CLASS_DEFAULT', Monolog\Formatter\JsonFormatter::class),
            'constructor' => [
                'format' => null,
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
    // 自监控
    'monitor' => [
        'handler' => [
            'class' => Monolog\Handler\RotatingFileHandler::class,
            'constructor' => [
                'filename' => env('LOG_BASE_PATH', BASE_PATH . '/runtime/logs') . '/monitor.log',
                'level' => Monolog\Logger::toMonologLevel(env('LOG_LEVEL_MONITOR', 'WARNING')),
                'maxFiles' => (int) env('LOG_MAX_FILES_DEFAULT', 7),
            ],
        ],
        'formatter' => [
            'class' => env('LOG_FORMATTER_CLASS_MONITOR', Monolog\Formatter\JsonFormatter::class),
            'constructor' => [
                'format' => null,
                'dateFormat' => null,
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
    // 通知SDK的日志channel
    'noticer' => [
        'handler' => [
            'class' => Monolog\Handler\RotatingFileHandler::class,
            'constructor' => [
                'filename' => env('LOG_BASE_PATH', BASE_PATH . '/runtime/logs') . '/noticer.log',
                'level' => Monolog\Logger::toMonologLevel(env('LOG_LEVEL_NOTICER', 'WARNING')),
                'maxFiles' => (int) env('LOG_MAX_FILES_DEFAULT', 7),
            ],
        ],
        'formatter' => [
            'class' => env('LOG_FORMATTER_CLASS_NOTICER', Monolog\Formatter\JsonFormatter::class),
            'constructor' => [
                'format' => null,
                'dateFormat' => null,
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
];
