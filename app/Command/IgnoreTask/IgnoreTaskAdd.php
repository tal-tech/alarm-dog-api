<?php

declare(strict_types=1);

namespace App\Command\IgnoreTask;

use App\Model\Config;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @Command
 */
class IgnoreTaskAdd extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('ignore-task:add');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Add the configure of the self-monitor taskids');
        $this->addOption('ids', 'I', InputOption::VALUE_REQUIRED, '要添加的任务ID');
    }

    public function handle()
    {
        $ids = $this->input->getOption('ids');

        // 从参数中取出id
        $allIds = [];
        foreach (explode(',', $ids) as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }

            $allIds[] = $id;
        }

        // 合并已有的配置
        $allIds = array_merge($allIds, Config::getItems(Config::KEY_IGNORE_SM_TASKIDS));

        $allIds = array_unique($allIds);
        sort($allIds, SORT_NUMERIC);

        $value = implode(',', $allIds);
        Config::updateConfig(Config::KEY_IGNORE_SM_TASKIDS, $value);

        $this->info(sprintf('updated the configure with value: %s', $value));
    }
}
