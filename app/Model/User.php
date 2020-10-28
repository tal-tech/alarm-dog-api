<?php

declare(strict_types=1);

namespace App\Model;

use App\Constants\ErrorCode;
use App\Exception\AppException;
use App\Service\Pinyin;
use App\Support\DingWorker;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;

class User extends Model
{
    // 普通用户角色
    public const ROLE_DEFAULT = 0;

    public $timestamps = false;

    public static $dingWorkerRemovePrefix = '好未来教育科技集团-';

    protected $table = 'user';

    protected $fillable = [
        'uid', 'username', 'email', 'department', 'phone', 'wechatid', 'role', 'created_at', 'updated_at',
    ];

    /**
     * @Inject
     * @var DingWorker
     */
    protected $dingworker;

    /**
     * @Inject
     * @var Pinyin
     */
    protected $pinyin;

    /**
     * 保存增量的用户.
     *
     * @param array $users 用户ID列表
     */
    public function saveIncrementUsers($users)
    {
        // 去重
        $users = array_unique($users);

        // 查询是否有新用户
        $exists = User::whereIn('uid', $users)->pluck('uid')->toArray();

        // 新增用户
        $news = array_diff($users, $exists);
        if (! empty($news)) {
            // 通过钉钉查询数据入库
            $inserts = [];
            foreach ($news as $uid) {
                $list = $this->dingworker->searchUser($uid);
                if (empty($list)) {
                    throw new AppException(sprintf('用户 [%s] 不存在', $uid), [], null, ErrorCode::NOT_FOUND);
                }

                $user = $this->parseDingWorkerUser($list[0]);
                // 过滤掉用户id为空的
                if ($user['uid'] != $uid) {
                    throw new AppException(sprintf('用户 [%s] 不存在', $uid), [], null, ErrorCode::NOT_FOUND);
                }
                $inserts[] = $user;
            }

            if (! empty($inserts)) {
                DB::table('user')->insert($inserts);
            }
        }
    }

    /**
     * 解析钉钉返回的用户信息.
     */
    public function parseDingWorkerUser(array $user): array
    {
        if (mb_strpos($user['department'], static::$dingWorkerRemovePrefix) === 0) {
            $user['department'] = mb_substr($user['department'], mb_strlen(static::$dingWorkerRemovePrefix));
        }

        return [
            'uid' => intval($user['workcode']),
            'username' => $user['name'],
            'pinyin' => $this->pinyin->name($user['name']),
            'user' => explode('@', $user['email'])[0],
            'email' => $user['email'],
            'department' => $user['department'],
            'role' => self::ROLE_DEFAULT,
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }
}
