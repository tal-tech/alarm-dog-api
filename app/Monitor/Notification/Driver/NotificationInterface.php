<?php

declare(strict_types=1);

namespace App\Monitor\Notification\Driver;

interface NotificationInterface
{
    public function send(string $content): void;

    public function getName(): string;
}
