<?php

declare(strict_types=1);

namespace App\Process;

use App\Model\AlarmTask;
use App\Model\Config;
use App\Support\ProcessMessage;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\AbstractProcess;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Event;
use Swoole\Process;
use Swoole\Server;
use Swoole\Timer;
use Throwable;

/**
 * 数据同步进程.
 */
class DataSyncProcess extends AbstractProcess
{
    /**
     * 进程数量.
     * @var int
     */
    public $nums = 1;

    /**
     * 进程名称.
     * @var string
     */
    public $name = 'ProcessDataSync';

    /**
     * @var int
     */
    public $pipeType = SOCK_DGRAM;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var StdoutLoggerInterface
     */
    protected $stdoutLogger;

    /**
     * @var FormatterInterface
     */
    protected $formatter;

    /**
     * @var AlarmTask
     */
    protected $alarmTask;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * 文件缓存路径.
     *
     * @var string
     */
    protected $fileCachePath;

    /**
     * @var int
     */
    protected $defaultRateLimit = 300;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->logger = $container->get(LoggerFactory::class)->get('dataSync');
        $this->formatter = $container->get(FormatterInterface::class);
        $this->stdoutLogger = $container->get(StdoutLoggerInterface::class);
        $this->config = $container->get(ConfigInterface::class);
        $this->alarmTask = $container->get(AlarmTask::class);

        $this->fileCachePath = $this->config->get('producer.data_sync_file_cache_path');
        if (! is_dir(dirname($this->fileCachePath))) {
            mkdir(dirname($this->fileCachePath), 0777, true);
        }

        $this->defaultRateLimit = (int) $this->config->get('producer.default_rate_limit');
    }

    public function handle(): void
    {
        // 立马同步一次
        if (! $this->dataSync()) {
            if ($data = $this->readFileCache()) {
                // 同步失败，但读取文件缓存成功，先使用文件缓存容灾
                ProcessMessage::broadcastToEventWorkers('dataSync', $data);
            } else {
                // 同步失败且无文件缓存，输出错误，杀死主进程
                $this->stdoutLogger->error('data sync failed, mysql and file cache all not working');
                /** @var Server */
                $server = $this->container->get(Server::class);
                Process::kill($server->master_pid, SIGTERM);
            }
        }

        $interval = (int) $this->config->get('producer.data_sync_interval', 10000);
        Timer::tick(
            $interval,
            function () {
                $this->dataSync();
            }
        );

        Event::wait();
    }

    /**
     * 数据同步.
     *
     * @return bool 同步成功返回true，否则false
     */
    protected function dataSync(): bool
    {
        try {
            $data = [
                'tasks' => $this->readTasks(),
                // 使用key-value的map存储，比对key时更快，value为bool类型占用内存更小
                'ignoreSmTaskids' => array_fill_keys(Config::getItems(Config::KEY_IGNORE_SM_TASKIDS), true),
            ];

            ProcessMessage::broadcastToEventWorkers('dataSync', $data);

            // 刷新文件文件缓存
            $this->writeFileCache($data);

            return true;
        } catch (Throwable $e) {
            // 记录异常日志
            $this->logger->warning($this->formatter->format($e));

            return false;
        }
    }

    /**
     * 写入文件缓存.
     * @param mixed $data
     */
    protected function writeFileCache($data): void
    {
        $cache = [
            'write_at' => time(),
            'data' => $data,
        ];
        file_put_contents($this->fileCachePath, json_encode($cache));
    }

    /**
     * 读取文件缓存.
     */
    protected function readFileCache(): ?array
    {
        if (! is_file($this->fileCachePath)) {
            $this->stdoutLogger->warning(sprintf('data sync file cache not exists in %s', $this->fileCachePath));
            return null;
        }
        $cache = file_get_contents($this->fileCachePath);
        $json = json_decode($cache, true);
        if (json_last_error() != JSON_ERROR_NONE || ! is_array($json) || ! isset($json['data'])) {
            $this->stdoutLogger->warning(sprintf('data sync file cache format is invalid in %s', $this->fileCachePath));
            return null;
        }

        $this->stdoutLogger->info(
            sprintf(
                'data sync using file cache successfully, please check mysql whether is working, because using file' .
                ' cache when mysql is exception.'
            )
        );
        $this->stdoutLogger->info(sprintf('file cache written time is %s', date('Y-m-d H:i:s', $json['write_at'])));

        return $json['data'];
    }

    /**
     * 读取任务数据.
     */
    protected function readTasks(): array
    {
        $tasks = [];
        foreach (AlarmTask::select('id', 'token', 'status', 'props')->get() as $task) {
            $tasks[$task['id']] = [
                'token' => (string) $task['token'],
                'status' => (int) $task['status'],
                'props' => $this->fmtTaskProps($task['props'] ?? null),
            ];
        }

        return $tasks;
    }

    /**
     * @param mixed $props
     */
    protected function fmtTaskProps($props): array
    {
        if (empty($props) || ! is_array($props)) {
            return [
                'rate_limit' => $this->defaultRateLimit,
            ];
        }

        return [
            'rate_limit' => (int) ($props['rate_limit'] ?? $this->defaultRateLimit),
        ];
    }
}
