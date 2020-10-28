<?php

declare(strict_types=1);

namespace App\Model;

class AlarmGroup extends Model
{
    // 短信通知
    public const CHANNEL_SMS = 'sms';

    // 电话通知
    public const CHANNEL_PHONE = 'phone';

    // 邮件通知
    public const CHANNEL_EMAIL = 'email';

    // 钉钉工作通知
    public const CHANNEL_DINGWORKER = 'dingworker';

    // 微信通知
    public const CHANNEL_WECHAT = 'wechat';

    // 钉钉群通知
    public const CHANNEL_DINGGROUP = 'dinggroup';

    // Yach群通知
    public const CHANNEL_YACHGROUP = 'yachgroup';

    // Yach工作通知
    public const CHANNEL_YACHWORKER = 'yachworker';

    // Webhook
    public const CHANNEL_WEBHOOK = 'webhook';

    /**
     * 可用的通知渠道且与用户相关.
     */
    public static $availableChannelsUser = [
        self::CHANNEL_SMS, self::CHANNEL_EMAIL, self::CHANNEL_PHONE, self::CHANNEL_DINGWORKER,
        self::CHANNEL_YACHWORKER,
    ];

    /**
     * 可用的通知渠道且与机器人相关.
     */
    public static $availableChannelsRobot = [
        self::CHANNEL_DINGGROUP, self::CHANNEL_YACHGROUP,
    ];

    /**
     * 可用的通知渠道.
     */
    public static $availableChannels = [
        self::CHANNEL_SMS, self::CHANNEL_EMAIL, self::CHANNEL_PHONE, self::CHANNEL_DINGWORKER, self::CHANNEL_DINGGROUP,
        self::CHANNEL_YACHGROUP, self::CHANNEL_YACHWORKER, self::CHANNEL_WEBHOOK,
    ];

    protected $table = 'alarm_group';

    protected $fillable = [
        'name', 'remark', 'created_by', 'created_at', 'updated_at',
    ];

    protected $timestamp = false;
}
