<?php

declare(strict_types=1);

use App\Monitor\Notification\Driver\DingGroup;
use App\Monitor\Notification\Driver\YachGroup;

return [
    'self_monitor_enable' => (bool) env('MONITOR_SELF_MONITOR_ENABLE', true),
    'default_notification' => explode(',', env('MONITOR_DEFAULT_NOTIFICATION', 'dinggroup')),
    'notifications' => [
        'dinggroup' => [
            'class' => DingGroup::class,
            'config' => [
                // webhook1:secret1|webhook2:secret2 格式
                'robots' => DingGroup::parseRobots(env('MONITOR_NOTIFICATIONS_DINGGROUP_ROBOTS')),
            ],
        ],
        'yachgroup' => [
            'class' => YachGroup::class,
            'config' => [
                // webhook1:secret1|webhook2:secret2 格式
                'robots' => YachGroup::parseRobots(env('MONITOR_NOTIFICATIONS_YACHGROUP_ROBOTS')),
            ],
        ],
    ],
];
