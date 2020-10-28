<?php

declare(strict_types=1);

namespace App\Model;

class Config extends Model
{
    /**
     * Key列表.
     */
    // 忽略自监控告警的任务ID
    public const KEY_IGNORE_SM_TASKIDS = 'ignore_sm_taskids';

    public $timestamps = false;

    protected $table = 'config';

    protected $fillable = ['key', 'remark', 'value', 'created_at', 'updated_at'];

    /**
     * 获取配置-Items.
     */
    public static function getItems(string $key, array $default = []): array
    {
        $value = self::where('key', $key)->value('value');
        if (empty($value)) {
            return $default;
        }

        return explode(',', $value);
    }

    /**
     * 更新配置.
     *
     * @param mixed $key
     * @param mixed $value
     * @param null|mixed $remark
     */
    public static function updateConfig($key, $value, $remark = null)
    {
        $config = self::where('key', $key)->first();
        if (empty($config)) {
            $config = self::create([
                'key' => $key,
                'value' => $value,
                'remark' => '',
                'created_at' => time(),
                'updated_at' => time(),
            ]);
        } else {
            $config['value'] = $value;
            $config['updated_at'] = time();
            if (! is_null($remark)) {
                $config['remark'] = $remark;
            }
            $config->save();
        }

        return $config;
    }
}
