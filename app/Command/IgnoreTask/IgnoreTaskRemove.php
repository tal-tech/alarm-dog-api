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
class IgnoreTaskRemove extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('ignore-task:remove');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Remove the configure of the self-monitor taskids');
        $this->addOption('ids', 'I', InputOption::VALUE_REQUIRED, '要移除的任务ID');
    }

    public function handle()
    {
        $ids = $this->input->getOption('ids');

        // 从参数中取出id
        $allIds = Config::getItems(Config::KEY_IGNORE_SM_TASKIDS);
        $allIds = array_flip($allIds);

        foreach (explode(',', $ids) as $id) {
            $id = (int) $id;
            if (array_key_exists($id, $allIds)) {
                unset($allIds[$id]);
            }
        }

        // 存储剩余的taskids
        $allIds = array_keys($allIds);
        sort($allIds, SORT_NUMERIC);

        $value = implode(',', $allIds);
        Config::updateConfig(Config::KEY_IGNORE_SM_TASKIDS, $value);

        $this->info(sprintf('updated the configure with value: %s', $value));
    }
}
