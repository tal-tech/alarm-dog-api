<?php

declare(strict_types=1);

namespace App\Monitor\Notification\Driver;

use Dog\Noticer\Channel\DingGroup as DingGroupChannel;
use Dog\Noticer\Channel\DingGroup\MsgType\Text as DingGroupText;

class DingGroup implements NotificationInterface
{
    /**
     * @var array
     */
    protected $robots = [];

    /**
     * @var DingGroupChannel
     */
    protected $sender;

    public function __construct(DingGroupChannel $sender, array $config)
    {
        $this->sender = $sender;
        $this->robots = $config['robots'];
    }

    public function send(string $content): void
    {
        $text = new DingGroupText($content);

        $this->sender->send($text, $this->robots);
    }

    public function getName(): string
    {
        return 'dinggroup';
    }

    /**
     * @param string $config webhook1:secret1|webhook2:secret2
     */
    public static function parseRobots(?string $config): array
    {
        $robots = [];
        if (empty($config)) {
            return $robots;
        }

        foreach (explode('|', $config) as $robotStr) {
            $robotConfig = explode(':', $robotStr);
            $robots[] = [
                'webhook' => $robotConfig[0],
                'secret' => $robotConfig[1] ?? null,
            ];
        }

        return $robots;
    }
}
