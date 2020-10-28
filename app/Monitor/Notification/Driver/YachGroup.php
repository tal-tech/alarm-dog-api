<?php

declare(strict_types=1);

namespace App\Monitor\Notification\Driver;

use Dog\Noticer\Channel\YachGroup as YachGroupChannel;
use Dog\Noticer\Channel\YachGroup\MsgType\Text as YachGroupText;

class YachGroup implements NotificationInterface
{
    /**
     * @var array
     */
    protected $robots = [];

    /**
     * @var YachGroupChannel
     */
    protected $sender;

    public function __construct(YachGroupChannel $sender, array $config)
    {
        $this->sender = $sender;
        $this->robots = $config['robots'];
    }

    public function send(string $content): void
    {
        $text = new YachGroupText($content);

        $this->sender->send($text, $this->robots);
    }

    public function getName(): string
    {
        return 'yachgroup';
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
