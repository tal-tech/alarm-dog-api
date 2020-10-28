<?php

declare(strict_types=1);

namespace App\Support;

use Hyperf\Framework\Logger\StdoutLogger as BaseStdoutLogger;
use Psr\Log\LogLevel;

class StdoutLogger extends BaseStdoutLogger
{
    protected function getMessage(string $message, string $level = LogLevel::INFO, array $tags)
    {
        $tag = null;
        switch ($level) {
            case LogLevel::EMERGENCY:
            case LogLevel::ALERT:
            case LogLevel::CRITICAL:
                $tag = 'error';
                break;
            case LogLevel::ERROR:
                $tag = 'fg=red';
                break;
            case LogLevel::WARNING:
            case LogLevel::NOTICE:
                $tag = 'comment';
                break;
            case LogLevel::INFO:
            default:
                $tag = 'info';
        }

        $template = sprintf('[%s] <%s>[%s]</>', date('Y-m-d H:i:s'), $tag, strtoupper($level));
        $implodedTags = '';
        foreach ($tags as $value) {
            $implodedTags .= (' [' . $value . ']');
        }

        return sprintf($template . $implodedTags . ' %s', $message);
    }
}
