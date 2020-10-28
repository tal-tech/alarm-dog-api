<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\ErrorCode;
use App\Model\AlarmTask;
use Hyperf\Di\Annotation\Inject;

class AlarmController extends AbstractController
{
    /**
     * @Inject
     * @var AlarmTask
     */
    protected $alarmTask;

    /**
     * 告警上报.
     */
    public function report()
    {
        $param = $this->request->all();
        if (empty($param['ctn'])) {
            return $this->failed('ctn is required', [], ErrorCode::INVALID_INPUT);
        }
        if (! is_array($param['ctn']) || array_keys($param['ctn']) === range(0, count($param['ctn']) - 1)) {
            return $this->failed('ctn must be a JSON object', [], ErrorCode::INVALID_INPUT);
        }

        if (empty($param['notice_time'])) {
            $param['notice_time'] = time();
        } elseif (! is_numeric($param['notice_time'])) {
            return $this->failed('notice_time must be integer', [], ErrorCode::INVALID_INPUT);
        }

        if (empty($param['level'])) {
            $param['level'] = AlarmTask::LEVEL_NOTICE;
        } elseif (! isset(AlarmTask::$levels[$param['level']])) {
            return $this->failed(
                'level must be in ' . implode(',', array_keys(AlarmTask::$levels)),
                [],
                ErrorCode::INVALID_INPUT
            );
        }

        $resp = $this->alarmTask->produce($param);

        return $this->success($resp);
    }

    /**
     * 告警测试
     * 用于校验任务ID、token是否正确.
     */
    public function test()
    {
        return $this->success();
    }

    /**
     * 阿里云告警.
     */
    public function aliyun()
    {
        $query = $this->request->query();
        $body = $this->request->post();

        $data = [
            'taskid' => $query['taskid'],
            'notice_time' => time(),
            'level' => AlarmTask::LEVEL_NOTICE,
        ];

        $resp = $this->alarmTask->produceForAliyun($data, $body);

        return $this->success($resp);
    }

    /**
     * falcon告警.
     */
    public function falcon()
    {
        $param = $this->request->all();
        $ctn = $param;
        unset($ctn['taskid'], $ctn['timestamp'], $ctn['sign']);

        $data = [
            'taskid' => $param['taskid'],
            'notice_time' => time(),
            'level' => AlarmTask::LEVEL_NOTICE,
            'ctn' => $ctn,
        ];

        $resp = $this->alarmTask->produce($data);

        return $this->success($resp);
    }

    /**
     * Grafana告警.
     */
    public function grafana()
    {
        $query = $this->request->query();
        $body = $this->request->post();

        $data = [
            'taskid' => $query['taskid'],
            'notice_time' => time(),
            'level' => AlarmTask::LEVEL_NOTICE,
        ];

        $resp = $this->alarmTask->produceForGrafana($data, $body);

        return $this->success($resp);
    }

    /**
     * json格式的body告警.
     */
    public function jsonBody()
    {
        $query = $this->request->query();
        $body = (string) $this->request->getBody()->getContents();

        // 判断body必须能解析为json
        $ctn = json_decode($body, true);
        if (! is_array($ctn) || array_keys($ctn) === range(0, count($ctn) - 1)) {
            return $this->failed('body must be a JSON Object', [], ErrorCode::INVALID_INPUT);
        }

        $data = [
            'taskid' => $query['taskid'],
            'notice_time' => time(),
            'level' => AlarmTask::LEVEL_NOTICE,
            'ctn' => $ctn,
        ];

        $resp = $this->alarmTask->produce($data);

        return $this->success($resp);
    }

    /**
     * rawBody告警.
     */
    public function rawBody()
    {
        $query = $this->request->query();
        $body = (string) $this->request->getBody()->getContents();

        $param = [
            'taskid' => $query['taskid'],
            'notice_time' => time(),
            'level' => AlarmTask::LEVEL_NOTICE,
            'ctn' => [
                'body' => $body,
            ],
        ];

        $resp = $this->alarmTask->produce($param);

        return $this->success($resp);
    }
}
